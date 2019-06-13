<?php

/**
 * @file
 * Contains \Drupal\.
 * 
 * 
 */

namespace Drupal\automatic_node_downloader\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\automatic_node_downloader\Controller\ANDController;
use Drupal\field\Entity\FieldStorageConfig;
use PHPExcel;
use PHPExcel_IOFactory;

require_once DRUPAL_ROOT . '/vendor/excellibrary/php-excel-reader/excel_reader2.php';
require_once DRUPAL_ROOT . '/vendor/excellibrary/SpreadsheetReader.php';
require_once DRUPAL_ROOT . '/vendor/excellibrary/xl_download_library/PHPExcel/Classes/PHPExcel.php';

class MidANDForm extends FormBase {

  protected $id;

  function getFormId() {
    return 'mid_automatic_node_downloader';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $curr_enabled_lang = \Drupal::languageManager()->getCurrentLanguage();
    $list_enabled_lang = \Drupal::languageManager()->getLanguages();

    // Menu level option array
    if (strpos($form_state->getValue('mid_menu_items'), ":menu_link_content:") > 0) {
      $levels_array = array("none" => "Select menu level", 0 => "1 Level", 1 => "2 Level", 2 => "3 Level", 3 => "4 Level", 4 => "5 Level", 5 => "6 Level");
    }
    else {
      $levels_array = array("none" => "Select menu level", 1 => "1 Level", 2 => "2 Level", 3 => "3 Level", 4 => "4 Level", 5 => "5 Level", 6 => "6 Level");
    }
    // Generate language option array.
    $languages_array['none'] = '-- Select Language --';
    $languages_array['all'] = 'Drupal default language';
    if (count($list_enabled_lang) >= 1) {
      foreach ($list_enabled_lang as $lang_code => $lang_obj) {
        $languages_array[$lang_code] = $lang_obj->getName();
      }
    }

    $lang_selected = (null !== ( $form_state->getValue('mid_languages'))) ? $form_state->getValue('mid_languages') : key($languages_array);
    $form['mid_languages'] = array(
      '#type' => 'select',
      '#title' => t('Select Language'),
      '#description' => t('Enabled the language.'),
      '#default_value' => $lang_selected,
      '#ajax' => [
        // Function to call when event on form element triggered.
        'callback' => '::mid_press_release_dependent_dropdown_callback',
      ],
      '#options' => $languages_array,
    );

    // Generate menu option array.
    $get_all_menu = FALSE;
    $lang_code = 'all';
    $menu_option_array = ANDController::_get_menu_array($lang_selected, $get_all_menu);
    $menu_selected = (null !== $form_state->getValue('mid_menu')) ? $form_state->getValue('mid_menu') : key($menu_option_array);
    $form['mid_menu'] = array(
      '#type' => 'select',
      '#title' => t('Select Menu'),
      '#description' => t('Available menu.'),
      '#default_value' => $menu_selected,
      '#ajax' => [
        'callback' => '::mid_press_release_dependent_dropdown_callback_menu',
      ],
      '#id' => 'mid-menu-item-id_test',
      '#prefix' => '<div id="dropdown-items-replace_test">',
      '#suffix' => '</div>',
      '#options' => $menu_option_array,
    );

    // Generate menu items option array.
    $menu_items_option_array = array();
    if ($menu_selected != "all") {
      $menu_items_option_array = array('none' => 'Select menu items') + ANDController::_get_menu_items_array($menu_selected);
    }
    $menu_item_selected = (null !== $form_state->getValue('mid_menu_items')) ? $form_state->getValue('mid_menu_items') : key($menu_items_option_array);
    $form['mid_menu_items'] = array(
      '#type' => 'select',
      '#title' => t('Select starting menu item'),
      '#description' => t('Select the starting menu here.'),
      '#default_value' => $menu_item_selected,
//    '#ajax' => [
//      'callback' => '::mid_press_release_menu_item_dropdown_callback',
//    ],
      '#id' => 'mid-menu-item-id',
      '#prefix' => '<div id="dropdown-items-replace">',
      '#suffix' => '</div>',
      '#options' => $menu_items_option_array,
    );

    $menu_level_selected = (null !== $form_state->getValue('mid_menu_level')) ? $form_state->getValue('mid_menu_level') : key($menu_items_option_array);
    $form['mid_menu_level'] = array(
      '#type' => 'select',
      '#title' => t('Menu depth'),
      '#ajax' => [
        'callback' => '::mid_press_release_menu_depth_dropdown_callback',
      ],
      '#id' => 'mid-menu-dept-level',
      '#prefix' => '<div id="dropdown-depth-replace">',
      '#suffix' => '</div>',
      '#default_value' => $menu_level_selected,
      '#description' => t('Please select depth levels down from selected menu.'),
      '#options' => $levels_array,
    );


    // Generate field names option array.
    $field_name_option_array = array("none" => "None fields");
    if (!empty($menu_item_selected)) {
      $field_name_option_array = ANDController::_get_field_names_array($menu_item_selected, $menu_level_selected);
    }

    $form['mid_field_names'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Fields to download'),
      '#prefix' => '<div id="dropdown-field-names-replace">',
      '#suffix' => '</div>',
      '#options' => $field_name_option_array,
      '#description' => t('Title field is mandatory so excluded from options.'),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'Download',
    );
    $form['reload'] = array(
      '#type' => 'button',
      '#value' => 'Reload',
      '#attributes' => array('onclick' => 'window.location.href = window.location.href; return false;'),
      '#suffix' => t("<br/>Note: Please reload the page for new file download."),
    );

    $form['#attached']['library'][] = 'automatic_node_downloader/drupal.automatic_node_downloader';

    return $form;
  }

  /**
   * Function to return the form select form on AJAX request.
   * @param type $form
   * @param type $form_state
   * @return type
   */
  public static function mid_press_release_dependent_dropdown_callback(array &$form, FormStateInterface $form_state) {
    // Instantiate an AjaxResponse Object to return.
    $ajax_response = new AjaxResponse();
    // Add a command to execute on form, jQuery .html() replaces content between tags.
    // In this case, we replace the desription with wheter the username was found or not.
    $ajax_response->addCommand(new HtmlCommand('#dropdown-items-replace_test', render($form['mid_menu'])));
    // Return the AjaxResponse Object.
    return $ajax_response;
  }

  /**
   * Function to return the form select form on AJAX request.
   * @param type $form
   * @param type $form_state
   * @return type
   */
  public static function mid_press_release_dependent_dropdown_callback_menu(array &$form, FormStateInterface $form_state) {
    // Instantiate an AjaxResponse Object to return.
    $ajax_response = new AjaxResponse();
    // Add a command to execute on form, jQuery .html() replaces content between tags.
    // In this case, we replace the desription with wheter the username was found or not.
    $ajax_response->addCommand(new HtmlCommand("#dropdown-items-replace", render($form['mid_menu_items'])));
    $ajax_response->addCommand(new HtmlCommand("#dropdown-field-names-replace", render($form['mid_field_names'])));
//    $ajax_response->addCommand(new HtmlCommand("#dropdown-depth-replace", render( $form['mid_menu_level'])));    
    // Return the AjaxResponse Object.
    return $ajax_response;
  }

  /**
   * Function to return the form select form on AJAX request.
   * @param type $form
   * @param type $form_state
   * @return type
   */
  public static function mid_press_release_menu_depth_dropdown_callback(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    // Add a command to execute on form, jQuery .html() replaces content between tags.
    // In this case, we replace the desription with wheter the username was found or not.
    $ajax_response->addCommand(new HtmlCommand("#dropdown-field-names-replace", render($form['mid_field_names'])));
//        $ajax_response->addCommand(new HtmlCommand("#dropdown-depth-replace", render( $form['mid_menu_level'])));        
    // Return the AjaxResponse Object.
    return $ajax_response;
  }

  /**
   * Function to return the form select form on AJAX request.
   * @param type $form
   * @param type $form_state
   * @return type
   */
  public static function mid_press_release_menu_item_dropdown_callback(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    // Add a command to execute on form, jQuery .html() replaces content between tags.
    // In this case, we replace the desription with wheter the username was found or not.
//        $ajax_response->addCommand(new HtmlCommand("#dropdown-depth-replace", render( $form['mid_menu_level'])));
    // Return the AjaxResponse Object.
    return $ajax_response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (null !== $form_state->getValue('op')) {
      // Validate the language selectionl.
      if ($form_state->getValue('mid_languages') == "none") {
        $form_state->setErrorByName('mid_languages', 'Please select the language.');
        return;
      }
      // Validate the Select menu field.
      if ($form_state->getValue('mid_menu') == "all") {
        $form_state->setErrorByName('mid_menu', "Please select the menu.");
        return;
      }

      // Validate Starting menu item.
      if (empty($form_state->getValue('mid_menu_items')) or $form_state->getValue('mid_menu_items') == "none") {
        $form_state->setErrorByName('mid_menu_items', "Please select the start menu item.");
        return;
      }
      // Validate Menu depth.
      if ($form_state->getValue('mid_menu_level') == "none") {
        $form_state->setErrorByName('mid_menu_level', "Please select menu depth.");
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   * 
   * Function generate the excel file data and excel file download header.
   * 
   * @param array $form
   * @param FormStateInterface $form_state
   */
  function submitForm(array &$form, FormStateInterface $form_state) {
    $menu_name = ( null !== $form_state->getValue('mid_menu') ) ? $form_state->getValue('mid_menu') : "";
    $menu_level_selected = (null !== $form_state->getValue('mid_menu_level') ) ? $form_state->getValue('mid_menu_level') : "";
    $menu_item_selected = (null !== $form_state->getValue('mid_menu_items') ) ? $form_state->getValue('mid_menu_items') : "";
    $selected_fields = (null !== $form_state->getValue('mid_field_names') ) ? $form_state->getValue('mid_field_names') : "";
    $selected_language = (null !== $form_state->getValue('mid_languages') ) ? $form_state->getValue('mid_languages') : "";

    //  ========================Header Start==========================
    // Basic header lables array structure.
    $header_labels_init = array(
      "nid",
      "src_lang",
      "level_index_1",
      "Level 1",
      "level_index_2",
      "Level 2",
      "level_index_3",
      "Level 3",
      "level_index_4",
      "Level 4",
      "level_index_5",
      "Level 5",
      "level_index_6",
      "Level 6",
      "content type",
      "path alias",
      "title"
    );

    // This code change menu level and add -1 to level.
//        $menu_check = explode(':', $menu_item_selected);
//        if($menu_check[1] == '') $menu_level_selected = $menu_level_selected - 1;
//        if(count($menu_check) < 3) $menu_level_selected = $menu_level_selected - 1;
    // Hide the unwanted level columns
    $header_labels = array();
    foreach ($header_labels_init as $key => $value) {
      if ($key <= ($menu_level_selected * 2) + 1 || ($key == 0 || $key > 13)) {
        $header_labels[] = $value;
      }
    }
    // This code change menu level and add +1 to level
//        if($menu_check[1] == 0) $menu_level_selected = $menu_level_selected + 1;
//        if(count($menu_check) < 3) {
//          $menu_level_selected = $menu_level_selected + 1;
//        }
    // Excluded fields array.
    $excluded_fields = array('all', 'title', 'uid', 'status', 'created', 'changed', 'sticky', 'path');

    // Get the selected fields labels for the display
    $fields_to_display = array();

    // Remove the excluded fields from selected fields.
    $selected_fields = array_diff($selected_fields, $excluded_fields);

    // Update the header labels array with the selected fields array.
    $header_labels = array_merge($header_labels, array_keys($selected_fields));

    $fields_to_display = $selected_fields;

    // Update if any new default field need to be added. 
    // Create new column as excel 
    $header_labels_updated_key = array();
    foreach ($header_labels as $key => $value) {
      if (strpos($value, "level_index") === 0) {
        $header_labels_updated_key[$key] = "";
      }
      else {
        $header_labels_updated_key[$key] = $value;
      }
    }

    // Array to get the index for field.
    $header_column_get_key = array_flip($header_labels_updated_key);

    // =========================Header End=============================
    // =========================Rows Start=============================

    $error = 0;
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    date_default_timezone_set('Europe/London');
    if (PHP_SAPI == 'cli')
      die('This example should only be run from a Web Browser');

    /** Include PHPExcel */
    // Create new PHPExcel object
    $objPHPExcel = new PHPExcel();
    // Set document properties
    $objPHPExcel->getProperties()->setCreator("Maarten Balliauw")
      ->setLastModifiedBy("Maarten Balliauw")
      ->setTitle("Office 2007 XLSX Test Document")
      ->setSubject("Office 2007 XLSX Test Document")
      ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
      ->setKeywords("office 2007 openxml php")
      ->setCategory("Test result file");

    // Set header to the excel file.
    $objPHPExcel->setActiveSheetIndex(0)->fromArray($header_labels_updated_key, '', 'A1');
    $excel_row_number = 2;
    $excel_col_number = 2;

    // Set the first three rows data.
    $objPHPExcel->getActiveSheet()
      ->setCellValueByColumnAndRow($excel_col_number, $excel_row_number++, "Field Type Specification")
      ->setCellValueByColumnAndRow($excel_col_number, $excel_row_number++, "Field Value Source");

    $temp_array = explode(":", $menu_item_selected);
    if (strpos($menu_item_selected, 'menu_link_content') !== false && !empty($temp_array[1]) && !empty($temp_array[2])) {
      $mlid_new = (int) ANDController::get_mlid_from_mlink_content($temp_array[1] . ':' . $temp_array[2]);
    }
    else {
      $mlid_new = 0;
    }
    $mlid = $mlid_new;
    unset($temp_array);
    $selected_menu_depth = "";

    if ($mlid > 0) {
      $menu_link = ANDController::get_menu_link_load($mlid);
      $selected_menu_depth = $menu_link[0]->depth;
    }

    $menu_items_option_array = ANDController::_get_menu_items_array($menu_name);

    // Start from root element.
    // create the empty row array for pulling data.
    $row_empty_array = array();
    foreach ($header_labels_updated_key as $value) {
      $row_empty_array[] = "";
    }
    // Level index array.
    $level_parent_counter = array(
      '1' => 0,
      '2' => 0,
      '3' => 0,
      '4' => 0,
      '5' => 0,
      '6' => 0
    );

    $data_row = array();
    $main_menu = 1;
    $menu_item_avaliable = array();
    $front_mlid = '';

    foreach ($menu_items_option_array as $key => $value) {
      if (strpos($key, 'menu_link_content') !== false) {
        $row_array = $row_empty_array;
        $key_array = array();
        $key_array = explode(":", $key);
        $key_array_mlid = "";
        if (!empty($key_array[1]) && !empty($key_array[2])) {
        $key_array_mlid = (int) ANDController::get_mlid_from_mlink_content($key_array[1] . ':' . $key_array[2]);
        }

        // Condition to check menu is not for default front menu or view menu.
        if ($key_array[1] != '' && (strpos($menu_item_selected, 'standard.front_page') == FALSE) && (strpos($menu_item_selected, 'views_view') == FALSE )) {
          $menu_link = ANDController::get_menu_link_load($key_array_mlid);

          $menu_link_path = '/' . str_replace('=', '/', $menu_link[0]->route_param_key);
//            if($menu_link[0]->route_name == '<front>'){
          if (($menu_link[0]->route_param_key == NULL) || (strpos($menu_link[0]->route_param_key, 'taxonomy_term=') !== false)) {
            $front_mlid = $menu_link[0]->mlid;
          }

          $path_check = '/node/*';
          if (null != $menu_link_path)
            $path_check = \Drupal::service('path.alias_manager')->getAliasByPath($menu_link_path);

          if ((!in_array($front_mlid, get_object_vars($menu_link[0])) || $front_mlid == '')) { // && ($menu_link[0]->depth <= $menu_level_selected)
            if ((($menu_link[0]->depth <= $menu_level_selected ) && ($mlid == 0)) ||
              ((($menu_link[0]->depth >= $selected_menu_depth) && ($menu_link[0]->depth < ($menu_level_selected + $selected_menu_depth))) && ($mlid > 0) && ( ANDController::_is_child_menu($mlid, $menu_link[0])))) {

              $depth = $menu_link[0]->depth - $selected_menu_depth;
              if ($depth == 0 && $mlid != 0) {
                // Selected menu option is start menu option and the menu item at root exist.
                $depth = 1;
              }
              else {
                if ($mlid != 0)
                // Root menu item exist and current menu is not start selected menu.
                  $depth += 1;
              }

              $home_page_flag = 0;
              // Set menu level label.
              // check the level is set to current menu here.
              if ($menu_level_selected != 0) {
                // Other than current menu item option selected.
//                       if ( $menu_link[0]->route_name == "<front>" ) {
//                            $home_page_flag = 1;
//                            $row_array[2] = 1;
//                            $row_array[ $header_column_get_key['Level ' . $depth] ] = unserialize($menu_link[0]->title);   
//                        }
//                        else {            
                $row_array[$header_column_get_key['Level ' . $depth]] = unserialize($menu_link[0]->title);
//                        }       
              }
              else {
                // Current menu item option selected for download.
                $row_array[$header_column_get_key['Level 1']] = unserialize($menu_link[0]->title);
              }

              if (strpos($menu_link[0]->route_param_key, 'node=') !== false) {
                // Get nid from the path
                $nid = unserialize($menu_link[0]->route_parameters)['node'];
                if (!empty($nid)) {
                  $node_data = node_load($nid);
                  $pathalias = ANDController::_get_path_alias_from_node_id('/node/' . $node_data->get('nid')->value);
                  // Set the menu level index
                  if ($menu_level_selected == 0) {
                    // Current menu item level option selected.
                    $row_array[$header_column_get_key['Level 1'] - 1] = '0';
                  }
                  else {
                    // Other than current menu item level option select.
                    $level_index = "";
                    foreach ($level_parent_counter as $key => $count) {
                      if ($depth == $key) {
                        if ($level_parent_counter[$key] == 0) {
                          $level_parent_counter[$key] = 1;
                          $level_index = implode(".", array_slice($level_parent_counter, 0, $key));
                        }
                        else {
                          $level_parent_counter[$key] ++;
                          // reset count for further levels
                          for ($i = $key + 1; $i <= count($level_parent_counter) - $key; $i++) {
                            $level_parent_counter[$i] = 0;
                          }
                          $level_index = implode(".", array_slice($level_parent_counter, 0, $key));
                        }
                      }
                    }
                    if ($main_menu == 1) {
                      $level_index_new = explode('.', $level_index);

                      foreach ($level_index_new as $index_key => $index_val) {
                        if ($index_key == 0) {
                          $level_index_new[0] = $level_index_new[0] - 1;
                        }
                      }
                      $level_index = implode(".", $level_index_new);
                    }
                    // Set the level key
                    if (array_key_exists('Level ' . $depth, $header_column_get_key)) {
                      $row_array[$header_column_get_key['Level ' . $depth] - 1] = $level_index;
                    }
                  }
                  // Set nid of node.
                  $row_array[$header_column_get_key['nid']] = $node_data->get('nid')->value;
                  //set Tnid of Node
                  $row_array[$header_column_get_key['src_lang']] = $node_data->get('langcode')->value;  //$selected_language
                  // Set type of the row
                  $row_array[$header_column_get_key['content type']] = $node_data->getType();
                  // Set path of the row
//                      $row_array[ $header_column_get_key[ 'path alias' ] ] = $node_data->getTranslation($node_data->language()->getId())->toUrl('canonical', ['absolute' => FALSE])->toString();
                  $row_array[$header_column_get_key['path alias']] = $pathalias;
                  // Set title of the row
                  $row_array[$header_column_get_key['title']] = $node_data->getTitle();

                  foreach ($fields_to_display as $key => $field_name) {
                    if ($node_data->hasField($field_name)) {
                      $field_info = FieldStorageConfig::loadByName('node', $field_name);
                      if ($field_info->getType() == 'datetime' || $field_info->getType() == 'date' || $field_info->getType() == 'datestamp') {
                        $column_position = $header_column_get_key[$field_name];
                        $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column_position, 2, "d-m-Y H:i:s");
                      }
                      // Set field of the row
                      $row_array[$header_column_get_key[$field_name]] = ANDController::_get_field_data($field_name, $node_data);
                    }
                  }
                }
              }

              $data_row[$excel_row_number] = $row_array;
              $objPHPExcel->setActiveSheetIndex(0)->fromArray($row_array, '', 'A' . $excel_row_number++);
            }
          }
        }
      }
    }

    // Rename worksheet
//        $language = \Drupal::languageManager()->getCurrentLanguage();
//        $file_lable = \Drupal::config('system.site')->get('name') . "_" . $language->getId();
    $file_lable = \Drupal::config('system.site')->get('name') . "_" . $selected_language;
    $sheet_title = "Sheet1";
    $file_name = $file_lable . "_" . date('d_m_y_h_i_s', time());
    $objPHPExcel->getActiveSheet()->setTitle($sheet_title);

    // Set active sheet index to the first sheet, so Excel opens this as the first sheet
    $objPHPExcel->setActiveSheetIndex(0);

    // Redirect output to a client’s web browser (Excel2007)
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $file_name . '.xlsx"');
    header('Cache-Control: max-age=0');

    // If you're serving to IE 9, then the following may be needed
    header('Cache-Control: max-age=1');

    // If you're serving to IE over SSL, then the following may be needed
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
    header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
    header('Pragma: public'); // HTTP/1.0
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $objWriter->save('php://output');
    exit;
  }

}
