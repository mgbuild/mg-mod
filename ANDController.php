<?php

/**
@file
Contains \Drupal\mid_redirection\Controller\AdminController.
 * 
 * 
 */

namespace Drupal\automatic_node_downloader\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Term;

define( "INTER_FIELD_VALUE_SEPRATOR", "||" );
define( "INTRA_FIELD_VALUE_SEPRATOR", "~~" );

class ANDController extends ControllerBase {
    
    /**
     * Function to get the languagewise menus array for select box. 
     * @param type $lang
     * @param type $all
     * @return type
     */
    public static function _get_menu_array( $lang, $all = FALSE ) {
      $menu_obj_array = ANDController::_get_language_wise_menu_objects( $lang, $all );
      $output = array();
      $output[ 'all' ] = "Select menu";
      foreach ( $menu_obj_array as $menu_name => $menu ) {
        $output[$menu_name] = $menu;
      }
      return $output;
    }
    
    public static function _get_field_info_instances($entity_type_id, $bundle){
        $bundleFields = array();
        foreach (\Drupal::entityManager()->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
            if (!empty($field_definition->getTargetBundle())) {
              $bundleFields[$entity_type_id][$field_name]['type'] = $field_definition->getType();
              $bundleFields[$entity_type_id][$field_name]['label'] = $field_definition->getLabel();
            }
        }
        return $bundleFields;
    }
    
    // Need to rebuild the function by using proper classes
    public static function get_mlid_from_mlink_content($id){
        $mlid = db_select('menu_tree', 'mt')
                ->fields('mt', array('mlid'))
                ->condition('id', $id)
                ->execute()
                ->fetchField();
        return $mlid;
    }
    
    public static function get_menu_link_load($id){
        $mlid = db_select('menu_tree', 'mt')
                ->fields('mt')
                ->condition('mlid', $id)
                ->execute()
                ->fetchAll();
        return $mlid;
    }
    
    public static function get_menu_load_links($menu_name){
        $menu_load_links = db_select('menu_tree', 'mt')
                ->fields('mt')
                ->condition('menu_name', $menu_name)
                ->execute()
                ->fetchAll();
        return $menu_load_links;
    }
    
    /*
 * get path alias from the internal path
 */

public static function _get_path_alias_from_node_id($path_alias){
  $source_url = db_select('url_alias', 'ua')
                ->fields('ua', array('alias'))
                ->condition('source', $path_alias)
                ->execute()
                ->fetchField();
  if(!empty($source_url)){
      return $source_url;
  }
}

  public static function contentTypeFields($contentType) {
    $entityManager = \Drupal::service('entity.manager');
    $fields = [];

    if(!empty($contentType)) {
        $fields = array_filter(
            $entityManager->getFieldDefinitions('node', $contentType), function ($field_definition) {
                return $field_definition instanceof FieldConfigInterface;
            }
        );
    }

    return $fields;      
}

    /**
 * This function generates the option array for fields.
 * @param type $menu_item_selected
 * @param type $menu_level_selected
 * @return type
 */
public static function _get_field_names_array( $menu_item_selected, $menu_level_selected ) {

            // get all the chield menu
            if ( $menu_level_selected == "none" or $menu_item_selected == "" ) {
              return array( "none" => "None fields" );
            }
             $temp_array = explode( ":", $menu_item_selected );
            if(strpos($menu_item_selected, 'menu_link_content') !== false && !empty($temp_array[1]) && !empty($temp_array[2])){
              $mlid = ANDController::get_mlid_from_mlink_content($temp_array[ 1 ].':'.$temp_array[ 2 ]);
    }
    else {
              $mlid = 0;  
            }
            if(!empty($temp_array[0])) {
            $menu_name = $temp_array[ 0 ];
            }
            unset( $temp_array );

            $sub_menu = array();
            $data_type = array();
            $fields_array = array();
            $all_children = ANDController::get_menu_load_links( $menu_name );
            
            if ( $mlid != 0 ) {
              $menu_link = ANDController::get_menu_link_load( $mlid );
            }
//            else {
//              $menu_link['depth'] = 0;
//            }
            //Get content type array.
            ANDController::_get_content_type_array( $all_children, $sub_menu, $data_type, $menu_link, $menu_level_selected, $mlid );
          
            foreach ( $data_type as $content_type ) {
              if ( !empty( $content_type ) ) {
                $content_type_array = ANDController::_get_field_info_instances( "node", $content_type ) ;
                $fields_array[ $content_type ] = array_keys($content_type_array['node']);
              }
            }
            
            $field_names_array = array();
            // Add the content types.
            foreach ( $fields_array as $content_type => $fields ) {
              foreach ( $fields as $value ) {
                if($value != 'promote'){
                   $field_names_array[ $value ][] = $content_type; 
                }
              }
            }

            $field_array_updated = array( "all" => "Select all" );
            foreach ( $field_names_array as $field_name => $content_type_array ) {
              $field_array_updated[ $field_name ] = $field_name . " ( " . implode( ", ", $content_type_array ) . " )";
            }
          
            unset( $fields_array );
            unset( $field_names_array );
            return $field_array_updated;
      }

    /**
     * Function to return the "type" (content type) array. 
     * @param type $all_children
     * @param type $sub_menu
     * @param type $data_type
     * @param type $menu_link
     * @param type $menu_level_selected
     * @param type $mlid
     */
    public static function _get_content_type_array( $all_children, &$sub_menu, &$data_type, &$menu_link, &$menu_level_selected, &$mlid ) {
        foreach ( $all_children as $child_menu ) {
       if (ANDController::_is_child_menu( $mlid, $child_menu ) ) {
          if($mlid != 0){
             $level = $child_menu->depth - $menu_link[0]->depth; 
        }
        else {
            $level = $child_menu->depth - 0;  
          }
          // Condition to check the level of the children.
          if ( $level >= 0 && $level <= $menu_level_selected ) {
            $sub_menu[] = $child_menu;
            $nid = explode( "=", $child_menu->route_param_key );
          $node = NULL;
          if (!empty($nid[1])) {
            $node = node_load( $nid[ 1 ] );
            
            // Collect the content type of the menu element linked to.
            if ( !empty( $data_type[ $node->bundle() ] ) ) {
              $data_type[ $node->bundle() ] = $node->bundle();
            }
            else {
              if ((null !== strpos($child_menu->route_name, 'node')) && (null !== strpos($child_menu->route_param_key, 'node='))) {
                $data_type[ $node->bundle() ] = $node->bundle();
              }
            }
          }
        }
      }
    }
  }

    /**
     * Function to check the parent is present in current menu.
     * @param type $mlid
     * @param type $child_menu
     * @return boolean
     */
    public static function _is_child_menu( $mlid, $child_menu ) {
        
      // Parent key array to search.
      $p_array = array( "p1", "p2", "p3", "p4", "p5", "p6", "p7", "p8", "p9" );
//      if($child_menu->route_name != '<front>' ){
      if(($child_menu->route_name != '<front>' || $child_menu->route_param_key != NULL)){
        foreach ( $p_array as $value ) {
          // Check the $mlid is present inthe parent position.
          if ( $child_menu->$value == $mlid ) {
            return TRUE;
          }
        }
      }
      return FALSE;
    }

    /**
     * Function return only items present under the supplied menu name.
     * @param type $menu_selected
     * @return type
     */
    public static function _get_menu_items_array( $menu_selected ) {
      $menu_parent_selector = \Drupal::service('menu.parent_form_selector');
      $options_cacheability = new CacheableMetadata();
      $options = $menu_parent_selector->getParentSelectOptions('', NULL, $options_cacheability);
      $keys = array_keys( $options );
      $items_keys = array_flip( preg_grep( "/^$menu_selected/", $keys, 0 ) );
      $items = array_intersect_key( $options, $items_keys );
      
      return $items;
    }
    
   /**
     * Function return the array of menu object available in Drupal.
     * @param type $lang_code
     * @param type $all
     * @return type
     */
    public static function _get_language_wise_menu_objects( $lang_code, $all = FALSE ) {
      if ( $custom_menus = ANDController::load_all_menus() ) {
        // Restrict/Filter the admin menus from download.
        if ( !$all ) {
          $system_menus = ANDController::menu_system_menus_list();
          $system_menus[ 'devel' ] = 'devel';
          $custom_menus = array_diff_key( $custom_menus, $system_menus );
        }
        
        $output_menus = array();
        if ( $lang_code != 'all' ) {
          foreach ( $custom_menus as $menu_name => $menu ) {
//            if ( (null !== $menu[ 'language' ]) && $lang_code == $menu[ 'language' ] ) {
              $menu_entity = entity_load('menu', $menu_name);
              $lang_entity = $menu_entity->language()->getId();
              if($lang_entity == $lang_code){
                 $output_menus[ $menu_name ] = $menu; 
              }
//            }
          }
        }
        else {
          foreach ( $custom_menus as $menu_name => $menu ) {
            $output_menus[ $menu_name ] = $menu;
          }
        }
        asort( $output_menus );
      }
      return $output_menus;
    }
    
    /**
     * Listing of the system menu 
     */
    public static function menu_system_menus_list(){
        return array(
        'admin' => 'Administration',
        'footer' => 'Footer',
        'tools' => 'Tools',
        'account' => 'User account menu'
        );
    }
    
    /**
     * Load all menus from the system
     */
    public static function load_all_menus(){
        $all_menus = \Drupal\system\Entity\Menu::loadMultiple();
        $menus = array();
        foreach ($all_menus as $id => $menu) {
          $menus[$id] = $menu->label();
        }
        return $menus;
    }

    /**
    * Function to get the field values for each field selected.
    * @param type $field_machine_name
    * @param type $node_data
    * @param type $error
    * @return type
    */
    public static function _get_field_data( $field_machine_name, $node_data) {
      $field_info = FieldStorageConfig::loadByName('node', $field_machine_name );
     
      switch ( $field_info->getType()) {
        case "text_with_summary":
          // text with summary fields.
          $text_summary = $node_data->getTranslation($node_data->language()->getId())->get($field_machine_name)->getValue();
          $text_summary_array = array();
          if ( !empty( $text_summary ) ) {
            foreach ( $text_summary as $key => $value ) {
              $summary = !empty( $value[ 'summary' ] ) ? $value[ 'summary' ] : "none";
              $body = !empty( $value[ 'value' ] ) ? $value[ 'value' ] : "none";
              $text_summary_array[] = $summary . INTRA_FIELD_VALUE_SEPRATOR . $body;
            }
          }
          $output = implode( INTER_FIELD_VALUE_SEPRATOR, $text_summary_array );
          return $output;
        case "boolean":
          // list boolean fields.
          $list_boolean = $node_data->getTranslation($node_data->language()->getId())->get($field_machine_name)->getValue();
            $list_boolean_array = array();
          if ( !empty( $list_boolean ) ) {
            foreach ( $list_boolean as $key => $value ) {
              $list_boolean_array[] = $value[ 'value' ];
            }
          }
          $output = implode( INTER_FIELD_VALUE_SEPRATOR, $list_boolean_array );
          return $output;
        case "datetime":
        case "date":
        case "datestamp":
          // date time fields.
         $datetime = $node_data->getTranslation($node_data->language()->getId())->get($field_machine_name)->getValue();
         date_default_timezone_set('UTC');
         $datetime_array = array();
          if ( !empty( $datetime ) ) {
            foreach ( $datetime as $key => $value ) {
              $end_date = !empty( $value[ 'value2' ] ) ? $value[ 'value2' ] : "none";
              $start_date = !empty( $value[ 'value' ] ) ? $value[ 'value' ] : "none";

              if($field_info->getType() == 'datetime'){
                  $start_date = gmdate('d-m-Y H:i:s', strtotime($start_date));
                  if ( $end_date != "none" ) 
                  $end_date = gmdate('d-m-Y H:i:s', strtotime($end_date));
              }
              elseif($field_info->getType() == 'date'){
                  $start_date = gmdate('d-m-Y H:i:s', strtotime($start_date));
                  if ( $end_date != "none" ) 
                  $end_date = gmdate('d-m-Y H:i:s', strtotime($end_date));
              }
              elseif($field_info->getType() == 'datestamp'){
                  $start_date = gmdate('d-m-Y H:i:s', $start_date);
                  if ( $end_date != "none" )
                  $end_date = gmdate('d-m-Y H:i:s', $end_date);
              }
              // Check the end date is present.
              if ( $end_date != "none" ) {
                $datetime_array[] = $start_date . INTRA_FIELD_VALUE_SEPRATOR . $end_date;
              }
              else {
                // Only start date is present.
                $datetime_array[] = $start_date;
              }
            }
          }
          $output = implode( INTER_FIELD_VALUE_SEPRATOR, $datetime_array );
          return $output;
        case "entity_reference":
          $entityreference = $node_data->getTranslation($node_data->language()->getId())->get($field_machine_name)->getValue();
          $entity_list = array();
          if(!empty($entityreference)){
            foreach($entityreference as $entityreference_key => $entityreference_values){
                $entity_list[] = $entityreference_values['target_id'];
            }
          }
          $field_output = Term::loadMultiple($entity_list);
          
          $entityreference_array = array();
          if ( !empty( $field_output ) ) {
            foreach ( $field_output as $key => $value ) {
              $entityreference_array[] = $value->getName();
            }
          }
          $output = implode( INTER_FIELD_VALUE_SEPRATOR, $entityreference_array );
          return $output;
        case "file":
        case "image":
          $file = $node_data->getTranslation($node_data->language()->getId())->get($field_machine_name)->getValue();
          $file_array = array();
          global $base_url;
          if ( !empty( $file ) ) {
            foreach ( $file as $key => $value ) {
              $file_load = File::load($value['target_id']);
              $file_path = $file_load->getFileUri();
              $url = pathinfo(file_create_url($file_path));
              $file_array[] = $url['basename'];
            }
          }
          $output = implode( INTER_FIELD_VALUE_SEPRATOR, $file_array );
          return $output;
        case "list_float":
        case "list_integer":
        case "list_string":
          $items = $node_data->getTranslation($node_data->language()->getId())->get($field_machine_name)->getValue();
          $item_array = array();
          //$field_details = FieldStorageConfig::loadByName('node', $field_machine_name);
          $field_details = \Drupal::config('field.storage.node.'.$field_machine_name)->get();
          if ( !empty( $items ) ) {
            foreach ( $items as $key => $value ) {
              $item_array[] = $field_details['settings']['allowed_values'][$key]['label'];
            }
          }
          $output = implode( INTER_FIELD_VALUE_SEPRATOR, $item_array );
          return $output;
        case "text":
        case "text_long":
        case "string": 
        case "string_long":
        case "email":
        case "decimal":
        case "float":
        case "integer":
          $items = $node_data->getTranslation($node_data->language()->getId())->get($field_machine_name)->getValue();
            $item_array = array();
          if ( !empty( $items ) ) {
            foreach ( $items as $key => $value ) {
              $item_array[] = $value[ 'value' ];
            }
          }
          $output = implode( INTER_FIELD_VALUE_SEPRATOR, $item_array );
          return $output;
        default:
        \Drupal::logger('automatic_node_downloader')->notice($field_info->getType().' type field does not exist.');
        break;
      }
    }

}