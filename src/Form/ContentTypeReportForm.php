<?php
/**
 * @file
 * Contains \Drupal\content_type_report\Form.
 */
namespace Drupal\content_type_report\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class ContentTypeReportForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_type_report_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['content_type_report.settings'];
  }

  /**
   * {@inheritdoc}
   * Form
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $contentTypes = \Drupal::service('entity.manager')->getStorage('node_type')->loadMultiple();

    $contentTypesList = [];
    if(!empty($contentTypes)) {
      foreach ($contentTypes as $contentType) {
        $contentTypesList += [ $contentType->id() => $contentType->label() ];
      }
    }

    $config = $this->config('content_type_report.settings');
    $form['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => t('Content types'),
      //'#default_value' => $workflow_options,
      '#options' => $contentTypesList,
      '#default_value' => $config->get('content_types') ? $config->get('content_types') : [],
    ];

    return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   * Submit
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('content_type_report.settings')
      ->set('content_types', $form_state->getValue('content_types'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}