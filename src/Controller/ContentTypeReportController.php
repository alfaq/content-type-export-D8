<?php

namespace Drupal\content_type_report\Controller;

use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Url;
use Drupal\Core\Link;


class ContentTypeReportController extends ControllerBase{

  private $types = [];
  private $filename;
  private $nodes;
  private $fields = ['nid', 'title', 'created', 'changed', 'type'];

  private function getCheckedContentTypes(){
    //get checked types from settings page
    $content_types_report = \Drupal::config('content_type_report.settings')->get('content_types');
    if(!empty($content_types_report)) {
      return array_filter($content_types_report);
    }
    return null;
  }

  private function getNodesByType(){
    $query = \Drupal::database()->select('node_field_data', 'nfd');
    $query->fields('nfd', $this->fields);

    $conditions = new Condition('OR');
    foreach ($this->types as $type) {
      $conditions->condition('nfd.type', $type);
    }
    $query->condition($conditions);


    $query->orderBy('nfd.type', 'ASC');
    $nodes = $query->execute()->fetchAllAssoc('nid');
    return $nodes;
  }

  # create List
  public function content_type_report_list(){

    //get all types
    $contentTypes = \Drupal::service('entity.manager')->getStorage('node_type')->loadMultiple();

    $content_types_report = $this->getCheckedContentTypes();

    $header = array();
    $header[] = array('data' => $this->t('Content type'));
    $header[] = array('data' => $this->t('Description'));
    $header[] = $this->t('Operations');

    $rows = array();

    if(!empty($content_types_report)) {
      foreach ($content_types_report as $type) {
        $row = array();

        $url = \Drupal\Core\Url::fromRoute('content_type_report.type', ['type' => $type], ['absolute' => TRUE])->toString();
        $row['data']['type'] = Link::fromTextAndUrl($contentTypes[$type]->get('name'), Url::fromUri($url))->toString();
        $row['data']['description'] = $contentTypes[$type]->getDescription();

        $operations = array();
        $operations['export'] = array(
          'title' => $this->t('Export'),
          'url' => Url::fromRoute('content_type_report.export', ['type' => $type]),
        );
        $operations['view'] = array(
          'title' => $this->t('View'),
          'url' => Url::fromRoute('content_type_report.type', ['type' => $type]),
        );

        $row['data']['operations'] = array(
          'data' => array(
            '#type' => 'operations',
            '#links' => $operations,
          ),
        );

        $rows[] = $row;
      }
    }

    $build['path_table'] = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No content types'),
    );
    $build['path_pager'] = array('#type' => 'pager');

    return $build;
  }

  # view page
  public function content_type_report_type($type){
    $this->types = [$type];

    $this->nodes = $this->getNodesByType();
    $node_type = \Drupal::service('entity.manager')->getStorage('node_type')->load($type)->get('name');

    //kint($nodes);

    $header = array();
    $header[] = array('data' => $this->t('Title'));
    $header[] = array('data' => $this->t('Created'));
    $header[] = array('data' => $this->t('Last updated'));
    $header[] = array('data' => $this->t('Path'));
    $header[] = array('data' => $this->t('Content type'));

    $rows = array();
    $row = array();

    if(!empty($this->nodes)) {
      foreach ($this->nodes as $node) {
        $row = array();

        $row['data']['title'] = $node->title;
        $row['data']['created'] = date('d-m-Y H:i:s', $node->created);
        $row['data']['changed'] = date('d-m-Y H:i:s', $node->changed);

        $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $node->nid], ['absolute' => TRUE])->toString();
        $row['data']['path'] = Link::fromTextAndUrl($url, Url::fromUri($url, array('attributes' => array('target' => '_blank'))))->toString();

        $row['data']['type'] = $node_type;

        $rows[] = $row;
      }
      $rows[] = $row;
    }

    $build['path_table'] = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('Empty list'),
    );
    $build['path_pager'] = array('#type' => 'pager');

    return $build;
  }

  /*
   * Export
   */
  private function createExportFile(){
    $response = new Response();
    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="'.$this->filename.'"');

    $handle = fopen('php://temp', 'w+');

    // Add the header of the CSV file
    fputcsv($handle, array('Title', 'Created', 'Last updated', 'Path', 'Content type'),',');
    // Query data from database
    //$results = $this->connection->query("Replace this with your query");
    // Add the data queried from database
    foreach($this->nodes as $node) {
      $node_type = \Drupal::service('entity.manager')->getStorage('node_type')->load($node->type)->get('name');
      $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $node->nid], ['absolute' => TRUE])->toString();

      fputcsv(
        $handle, // The file pointer
        array($node->title, date('d-m-Y H:i:s', $node->created), date('d-m-Y H:i:s', $node->changed), $url, $node_type), ',' // The delimiter
      );
    }

    rewind($handle);
    $contents = stream_get_contents($handle);

    fclose($handle);

    $response->setContent($contents);

    return $response;
  }

  # export page
  public function content_type_report_export($type){
    $this->types = [$type];
    $this->nodes = $this->getNodesByType();
    $this->filename = $type.': '.date('d-m-Y H:i:s').'.csv';
    return $this->createExportFile();
  }

  #export all
  public function content_type_report_export_all(){
    $this->types = $this->getCheckedContentTypes();
    $this->nodes = $this->getNodesByType();
    $this->filename = 'All content types: '.date('d-m-Y H:i:s').'.csv';
    return $this->createExportFile();
  }

}