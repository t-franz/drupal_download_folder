<?php

namespace Drupal\custom_download_folder\Form;

// use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Custom Download Folder settings for this site.
 */
// class SettingsForm extends ConfigFormBase {
  class SettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_download_folder_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['custom_download_folder.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['folder'] = [
      '#type' => 'select',
      '#title' => t('Select folder to download'),
      '#options' => [
        '.' => 'all',
        'sites' => 'sites',
        'sites/default/files' => 'files',
        'themes' => 'themes',
        'modules' => 'modules',
        'libraries' => 'libraries',
      ],
      '#default_value' => (isset($_GET['folder'])) ? $_GET['folder'] : 'themes',
    ];
    $form['skip'] = [
      '#type' => 'checkbox',
      '#title' => t('Remove contributed and generated files'),
      '#default_value' => (isset($_GET['skip'])) ? $_GET['skip'] : TRUE,
      '#description' => t('For example') . ' <em>core</em>, <em>files/styles</em>, <em>files/css</em>, <em>files/js</em> ...',
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Download selected folder'),
      '#weight' => 90,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
/*     if ($form_state->getValue('example') != 'example') {
      $form_state->setErrorByName('example', $this->t('The value is not correct.'));
    } */
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $folder = '';
    $skip = TRUE;
    $folder = $form_state->getValue('folder');
    $skip = ($form_state->getValue('skip') == 1) ? TRUE : FALSE;

    $foldername = str_replace('.','all',$folder);

    $success = buildzipfile($folder,$skip);
    // Not Working
/*     if ($success) {
      \Drupal::messenger()->addMessage('Downloaded folder: '.$foldername);
    } else {
      \Drupal::messenger()->addMessage('Could not download folder: '.$foldername);
    }

    $url = \Drupal\Core\Url::fromRoute('custom_download_folder.settings_form')->setRouteParameters(
      array(
        'folder'=>$folder,
        'skip'=>$skip,
      )
    );
    $form_state->setRedirectUrl($url); */

  }
}





function buildzipfile($folder,$skip) {
  // Get real path for our folder
  $rootPath = realpath($folder);
  $host = \Drupal::request()->getHost();

  $zip_name = str_replace('/','-',$folder);
  $zip_name = str_replace('.','all',$zip_name);
  //$zip_name = 'download_'.urlencode($zip_name);
  $zip_name = urlencode($host).'_'.urlencode($zip_name);
  // Initialize archive object
  $zip_path =\Drupal::service('file_system')->saveData('','temporary://'.$zip_name.'.zip');
  $zip = \Drupal::service('plugin.manager.archiver')->getInstance(['filepath' => $zip_path]);

  //define the directories you don't want to include
  $excludeDirs = [];
  if ($skip){
    switch ($folder) {
      case 'sites':
        $excludeDirs = [
          'css','js','styles','translations',
        ];
      break;
      case 'sites/default/files':
        $excludeDirs = [
          'css','js','styles','translations',
        ];
      break;
      case '.':
        $excludeDirs = [
          'core','styles',
        ];
      break;
    }
  }


  //https://stackoverflow.com/a/32297064/1268812

  // Create recursive directory iterator
  /** @var SplFileInfo[] $files */
  $dir = new \RecursiveDirectoryIterator($rootPath);
  $files = new \RecursiveCallbackFilterIterator($dir, function($file, $key, $iterator) use ($excludeDirs){
      if($iterator->hasChildren() && !in_array($file->getFilename(), $excludeDirs)){
          return true;
      }
      return $file->isFile();
  });

  foreach(new \RecursiveIteratorIterator($files) as $file){
      // Skip directories (they would be added automatically)
      if (!$file->isDir()) {
          $filePath = $file->getRealPath();
          if (!strpos($filePath, '.DS_Store')){
            $zip->add($filePath);
          }
      }
  }

  $downloadfile = 'temporary://'.$zip_name.'.zip';

  if(file_exists($downloadfile)) {
    header("Content-type: application/zip");
    header("Content-Disposition: attachment; filename=\"$zip_name\"");
    readfile($downloadfile);
  }

  return true;
}
