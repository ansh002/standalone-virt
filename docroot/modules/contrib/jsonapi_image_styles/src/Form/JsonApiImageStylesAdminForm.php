<?php

namespace Drupal\jsonapi_image_styles\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Class JsonApiImageStylesAdminForm.
 */
class JsonApiImageStylesAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'jsonapi_image_styles.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'jsonapi_image_styles_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('jsonapi_image_styles.settings');

    $options = [];
    $styles = ImageStyle::loadMultiple();
    foreach ($styles as $name => $style) {
      $options[$name] = $style->label();
    }

    $form['image_styles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Image styles'),
      '#description' => $this->t('Select image styles to expose for JSON:API. If none are selected, all styles are exposed.'),
      '#options' => $options,
      '#default_value' => (is_array($config->get('image_styles'))) ? $config->get('image_styles') : [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('jsonapi_image_styles.settings')
      ->set('image_styles', $form_state->getValue('image_styles'))
      ->save();
  }

}
