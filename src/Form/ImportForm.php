<?php

namespace Drupal\feed_bulk_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;
use Drupal\node\Entity\Node;
use SimpleXMLElement;

class ImportForm extends FormBase {

  private $items = [];

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'feed_bulk_import_form';
  }

  /**
   * Retrieve processed items from feed.
   *
   * @return array
   */
  private function getAllItems(){
    $session = $this->getRequest()->getSession();
    $session_items = $session->get('feed_items');

    if(empty($this->items) && !empty($session_items)){
      $this->items = $session_items;
    }

    return $this->items;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $session = $this->getRequest()->getSession();

    if(!$form_state->isSubmitted()){
      // First step
      $form = [
        'step' => [
          '#type' => 'hidden',
          '#value' => 0
        ],
        'feed_url' => [
          '#type' => 'url',
          '#title' => $this->t('Feed URL'),
        ],
        'lang' => [
          '#type' => 'language_select',
          '#title' => $this->t('Import Language'),
          '#languages' => Language::STATE_CONFIGURABLE
        ],
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Process')
        ]
      ];
    }
    else{
      // Second step
      try{
        $x = new SimpleXMLElement($form_state->getValue('feed_url'), 0, TRUE);
      }catch (\Exception $ex){
        $x = FALSE;
      }

      if (!$x) {
        $form['empty'] = [
          '#markup' => $this->t('This URL either contains an empty feed or is incorrect.')
        ];
        //TODO try again button
      }
      else{
        $feed = (property_exists($x, 'entry')) ? 'atom' : 'rss';
        if ($feed == 'rss'){
          $iterator = $x->channel->item;
          $brief_key = 'description';
        }
        else{
          $iterator = $x->entry;
          $brief_key = 'summary';
        }

        $i = 1;
        $options = [];
        foreach ($iterator as $item) {
          $this->items[$i] = [
            'title' => $item->title->__toString(),
            'desc' => $item->$brief_key->__toString(),
            'url' => ($feed == 'rss') ? $item->link->__toString() : $item->link->attributes()->href->__toString(),
            //TODO Check for image
          ];

          $options[$i] = "<a href='{$this->items[$i]['url']}' target='_blank'>{$this->items[$i]['title']}</a>";
          $i++;
        }
        $session->set('feed_items', $this->items);

        $form['step'] = [
          '#type' => 'hidden',
          '#value' => 1
        ];

        $form['lang'] = [
          '#type' => 'hidden',
          '#value' => $form_state->getValue('lang')
        ];

        $form['items'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Select articles for import...'),
          '#options' => $options
        ];

        $form['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Import')
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('step')) {
      // Import
      $items = $this->getAllItems();
      $ids = array_filter($form_state->getValue('items'));

      foreach ($ids as $id){
        $body = "{$items[$id]['desc']}<p><a href='{$items[$id]['url']}' target='_blank'>{$this->t('Read more here')}</a></p>";

        Node::create([
          'type' => 'article',
          'title' => $items[$id]['title'],
          'body' => [
            'value' => $body,
            'format' => 'basic_html'
          ],
          'status' => 1,
          'langcode' => $form_state->getValue('lang'),
        ])->save();
      }

      \Drupal::messenger()->addStatus($this->t('Imported Successfully.'));
      $session = $this->getRequest()->getSession();
      $session->remove('feed_items');
    }
    else {
      // Process
      $form_state->setRebuild();
    }
  }
}
