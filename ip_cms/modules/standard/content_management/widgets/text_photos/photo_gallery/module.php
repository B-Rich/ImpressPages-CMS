<?php
/**
 * @package	ImpressPages
 * @copyright	Copyright (C) 2011 ImpressPages LTD.
 * @license	GNU/GPL, see ip_license.html
 */

namespace Modules\standard\content_management\Widgets\text_photos\photo_gallery;

if (!defined('CMS')) exit;

const GROUP_KEY = 'text_photos';
const MODULE_KEY = 'photo_gallery';


require_once(BASE_DIR.LIBRARY_DIR.'php/file/functions.php');

class Module extends \Modules\standard\content_management\Widget{
  var $parameters;



  function init(){
    global $site;    
    $answer =
    '<script type="text/javascript"  src="'.BASE_URL.CONTENT_MODULE_URL.'text_photos/photo_gallery/module.js"></script>
    <script type="text/javascript"  src="'.BASE_URL.CONTENT_MODULE_URL.'text_photos/photo_gallery/photo.js"></script>
    <script type="text/javascript" >
    //<![CDATA[
     function f_mod_photo_gallery_after_photo_save(){
        eval(document.getElementById(\'mod_photo_gallery_action_after_photo_save\').value);
     }
     //]]>
    </script>
    <div style="display: none;"><input type="hidden" id="mod_photo_gallery_action_after_photo_save" value="" /></div>
    <iframe style="display: none; width: 0px; height: 0px; border: 0;" name="mod_photo_gallery_iframe" onload="f_mod_photo_gallery_after_photo_save()" width="400" height="200"></iframe> 
    
    
    ';

    $site->requireConfig('standard/content_management/widgets/'.GROUP_KEY.'/'.MODULE_KEY.'/config.php');
    $layouts = Config::getLayouts();
    $script = '';
    
    if(!isset($layouts) || sizeof($layouts) == 0){
      $layouts = array();
      $layouts[] = array('translation'=>'', 'name'=>'default');
    }
    
    foreach($layouts as $key => $layout){
      $script .= '<option value="'.addslashes($layout['name']).'" >'.addslashes($layout['translation']).'</option>';
    }
    
    if(sizeof($layouts) <=1)
      $script = '<div class="ipCmsModuleLayout hidden"><label class="ipCmsTitle">Layout: </label><select name="layout">'.$script.'</select></div>';
    else
      $script = '<div class="ipCmsModuleLayout"><label class="ipCmsTitle">Layout: </label><select name="layout">'.$script.'</select></div>';
        
    $answer .= '
    <script type="text/javascript" >
    //<![CDATA[
    mod_photo_gallery_layout = \''.$script.'\';
     //]]>
    </script>
    ';


    return $answer;
  }

  function getData($id) {
    $sql = "select * from `".DB_PREF."mc_text_photos_photo_gallery` where `id` = '".(int)$id."' ";
    $rs = mysql_query($sql);
    if(!$rs){
        trigger_error($sql.' '.mysql_error());
        return false;
    }

    $data = mysql_fetch_assoc($rs);
    
    if (!$data) {
        trigger_error('Can\'t find widget');
        return false;
    }
    
    $data['photos'] = array ();
    
    $sql2 = "select * from `".DB_PREF."mc_text_photos_photo_gallery_photo` where `photo_gallery` = '".(int)$data['id']."' order by `row_number` desc";
    $rs2 = mysql_query($sql2);
    if($rs2){
        while($photo = mysql_fetch_assoc($rs2)){
            $photo['photo'] = BASE_DIR.IMAGE_DIR.$photo['photo'];
            $photo['photo_big'] = BASE_DIR.IMAGE_DIR.$photo['photo_big'];
            
            $data['photos'][] = $photo; 
        }
    } else {
        trigger_error($sql2.' '.mysql_error());
    }
    
    return $data;
  }  

  function getLayout($id){
    $sql = "select * from `".DB_PREF."mc_text_photos_photo_gallery` where id = '".(int)$id."'";
    $rs = mysql_query($sql);
    if($rs){
      if($lock = mysql_fetch_assoc($rs)){
        $layout = $lock['layout'];
        return $layout;
      }
    } else {
      trigger_error($sql.' '.mysql_error());
    }
    return false;
  }

  function add_to_modules($mod_management_name, $collection_number, $module_id, $visible){ //add existing module from database to javascript array
     
    global $site;
    $site->requireTemplate('standard/content_management/widgets/'.GROUP_KEY.'/'.MODULE_KEY.'/template.php');
     
    $answer = "";
    $sql = "select id, title, photo, photo_big from `".DB_PREF."mc_text_photos_photo_gallery_photo` where photo_gallery = '".(int)$module_id."' order by row_number ";
    $rs = mysql_query($sql);
    if (!$rs)
    trigger_error("Can't get module information ".$sql);
    else{
      $answer = "";
      $answer .= '<script type="text/javascript">
                  //<![CDATA[
                  ';
      $answer .= "  var new_module = new content_mod_photo_gallery();";
      $answer .= "  var new_module_name = '".$mod_management_name.".' + ".$mod_management_name.".get_modules_array_name() + '[".$collection_number."]';";
      $answer .= "  new_module.init(".$collection_number.", ".$module_id.", ".$visible.", new_module_name, ".$mod_management_name.");";

      $answer .= "var photos = new Array();";
      $photos = array();
      while ($lock = mysql_fetch_assoc($rs)){
        $answer .= "var new_photo = new gallery_photo();";
        $answer .= "new_photo.init();";
        $answer .= "new_photo.set_title('".addslashes($lock['title'])."');";
        $answer .= "new_photo.set_existing_photo('".$lock['photo']."');";
        $answer .= "new_photo.set_existing_bigphoto('".$lock['photo_big']."');";
        $answer .= "new_photo.set_photo_id(".$lock['id']."); ";
        $answer .= "photos.push(new_photo);";
         
        if($lock['photo'])
        $lock['photo'] = BASE_URL.IMAGE_DIR.$lock['photo'];
        if($lock['photo_big'])
        $lock['photo_big'] = BASE_URL.IMAGE_DIR.$lock['photo_big'];

        $photos[] = $lock;
      }

      $answer .= "  new_module.preview_html = '".str_replace('script',"scr' + 'ipt", str_replace("\r", "", str_replace("\n", "' + \n '", str_replace("'", "\\'", Template::generateHtml($photos, $this->getLayout($module_id))))))."';";
      $answer .= "  new_module.layout = '".str_replace("\r", "", str_replace("\n", "' + \n '", str_replace("'", "\\'",$this->getLayout($module_id))))."';";
       

      $answer .= "  new_module.set_photos(photos);";
      $answer .= "  ".$mod_management_name.".get_modules().push(new_module);";
      $answer .= "  ";
      $answer .= " //]]> ";
      $answer .= "</script>";

       
    }
    return $answer;
  }

  function insert_photo($gallery_id, $number, $values){
    $new_name = $values['new_photo'.$number];
    if ($new_name != ""){
      $new_name = \Library\Php\File\Functions::genUnocupiedName($new_name, BASE_DIR.IMAGE_DIR);
    }

    $new_bigname = $values['new_bigphoto'.$number];
    if ($new_bigname != ""){
      $new_bigname = \Library\Php\File\Functions::genUnocupiedName($new_bigname, BASE_DIR.IMAGE_DIR);
    }


    if ($new_bigname != '' && $new_name != ''){
      copy(TMP_IMAGE_DIR.$values['new_photo'.$number], IMAGE_DIR.$new_name);
      copy(TMP_IMAGE_DIR.$values['new_bigphoto'.$number], IMAGE_DIR.$new_bigname);
    }

    $sql = "insert into `".DB_PREF."mc_text_photos_photo_gallery_photo` set title = '".mysql_real_escape_string($values['title'.$number])."', photo = '".mysql_real_escape_string($new_name)."', photo_big = '".mysql_real_escape_string($new_bigname)."', photo_gallery = '".(int)$gallery_id."', row_number = '".(int)$number."' ";
    $rs = mysql_query($sql);
    if (!$rs)
    $this->set_error("Can't insert new photo ".$sql." ".mysql_error());
  }

  function update_photo($photo_id, $number, $values){
    $new_name = $values['existing_photo'.$number];
    if (isset($values['new_photo'.$number]) && $values['new_photo'.$number] != null) {
      $new_name = \Library\Php\File\Functions::genUnocupiedName($new_name, BASE_DIR.IMAGE_DIR);
      copy(TMP_IMAGE_DIR.$values['new_photo'.$number], IMAGE_DIR.$new_name);
    }
    $new_bigname = $values['existing_bigphoto'.$number];
    if (isset($values['new_photo'.$number]) && $values['new_bigphoto'.$number] != null) {
      $new_bigname = \Library\Php\File\Functions::genUnocupiedName($new_bigname, BASE_DIR.IMAGE_DIR);
      copy(TMP_IMAGE_DIR.$values['new_bigphoto'.$number], IMAGE_DIR.$new_bigname);
    }

    $sql = "update `".DB_PREF."mc_text_photos_photo_gallery_photo` set title = '".mysql_real_escape_string($values['title'.$number])."', row_number = '".(int)$number."' where id = '".(int)$photo_id."'";
    $rs = mysql_query($sql);
    if (!$rs)
    $this->set_error("Can't update new photo ".$sql);

  }

  function delete_photo($photo_id, $number, $values){
    if($values['existing_photo'.$number.'_del'] != '' && file_exists(IMAGE_DIR.$values['existing_photo'.$number.'_del'])){
      unlink(IMAGE_DIR.$values['existing_photo'.$number.'_del']);
    }
    if($values['existing_bigphoto'.$number.'_del'] != '' && file_exists(IMAGE_DIR.$values['existing_bigphoto'.$number.'_del'])){
      unlink(IMAGE_DIR.$values['existing_bigphoto'.$number.'_del']);
    }
    $sql = "delete from `".DB_PREF."mc_text_photos_photo_gallery_photo` where id = '".(int)$photo_id."' ";
    $rs = mysql_query($sql);
    if (!$rs)
    $this->set_error("Can't delete photo ".$sql);
  }



  function create_new_instance($values){

    if (true){
      $sql = "insert into `".DB_PREF."mc_text_photos_photo_gallery` set layout= '".mysql_real_escape_string($values['layout'])."' ";
      $rs = mysql_query($sql);
      if(!$rs){
        return "Can't insert new module. ".$sql;
      }else{
        $sql = "select max(id) as max_id from `".DB_PREF."mc_text_photos_photo_gallery` where 1";
        $rs = mysql_query($sql);
        if (!$rs)
        $this->set_error("Can't get last inserted id ".$sql);
        else{
          $lock = mysql_fetch_assoc($rs);
          $sql = "insert into `".DB_PREF."content_element_to_modules` set".
                " row_number = '".(int)$values['row_number']."', element_id = '".(int)$values['content_element_id']."' ".
                ", group_key='text_photos', module_key='photo_gallery', module_id = '".(int)$lock['max_id']."'".
                ", visible= '".(int)$values['visible']."' ";
          $rs = mysql_query($sql);
          if (!$rs)
          $this->set_error("Can't asociate element to module ".$sql);
        }
      }
    }

    $i = 0;
    while(isset($values['title'.$i])){
      $this->insert_photo($lock['max_id'], $i, $values);
      $i++;
    }

    return null;
  }

  function update($values){


    $i = 0;
    while(isset($values['photo_id'.$i])){
      if($values['photo_id'.$i] != null)
      $this->update_photo($values['photo_id'.$i], $i, $values);
      else
      $this->insert_photo($values['id'], $i, $values);
      $i++;
    }


    $i = 0;
    while(isset($values['photo_id'.$i.'_del'])){
      if($values['photo_id'.$i.'_del'] !== null)
      $this->delete_photo($values['photo_id'.$i.'_del'], $i, $values);
      $i++;
    }

    $sql = "update `".DB_PREF."mc_text_photos_photo_gallery` set layout= '".mysql_real_escape_string($values['layout'])."' where id = ".(int)$values['id']." ";
    $rs = mysql_query($sql);
    if(!$rs){
      trigger_error($sql." ".mysql_error());
    }


    $sql = "update `".DB_PREF."content_element_to_modules` set".
      " row_number = '".(int)$values['row_number']."',  visible= '".(int)$values['visible']."' where module_key = '".mysql_real_escape_string(MODULE_KEY)."' and group_key = '".mysql_real_escape_string(GROUP_KEY)."' and module_id = '".(int)$values['id']."'";
    $rs = mysql_query($sql);
    if (!$rs)
    $this->set_error("Can't change element ".$sql);


    return;
  }

  function delete($values){


    $sql = "select id, title, photo, photo_big from `".DB_PREF."mc_text_photos_photo_gallery_photo` where photo_gallery = '".(int)$values['id']."' ";
    $rs = mysql_query($sql);
    while($lock = mysql_fetch_assoc($rs)){
      if($lock['photo'] != null)
      if ($lock['photo'] != '' && file_exists(IMAGE_DIR.$lock['photo'])){
        if(!unlink(IMAGE_DIR.$lock['photo'])){
          $this->set_error("Can't delete photo.");
        }
      }

      if($lock['photo_big'] != null)

      if($lock['photo_big'] != '' && file_exists(IMAGE_DIR.$lock['photo_big'])){
        if (!unlink(IMAGE_DIR.$lock['photo_big'])){
          $this->set_error("Can't delete photo.");
        }
      }
    }
    $sql = "delete from `".DB_PREF."mc_text_photos_photo_gallery_photo` where photo_gallery = '".(int)$values['id']."' ";
    $rs = mysql_query($sql);
    if(!$rs)
    trigger_error($sql." ".mysql_error());

    $sql = "delete from `".DB_PREF."content_element_to_modules` where module_id = '".(int)$values['id']."'  and group_key = '".mysql_real_escape_string(GROUP_KEY)."' and module_key = '".mysql_real_escape_string(MODULE_KEY)."'";
    if (!mysql_query($sql))
    $this->set_error("Can't delete element to module association ".$sql);
    else{
      $sql = "delete from `".DB_PREF."mc_text_photos_photo_gallery` where id = '".(int)$values['id']."' ";
      if (!mysql_query($sql))
      $this->set_error("Can't delete module ".$sql);
    }
    return;

  }

  function delete_by_id($id){

    $sql = "select id, title, photo, photo_big from `".DB_PREF."mc_text_photos_photo_gallery_photo` where photo_gallery = '".(int)$id."' ";
    $rs = mysql_query($sql);
    while($lock = mysql_fetch_assoc($rs)){
      if($lock['photo'] != null)
      if($lock['photo'] != '' && file_exists(IMAGE_DIR.$lock['photo'])){
        if (!unlink(IMAGE_DIR.$lock['photo'])){
          $this->set_error("Can't delete photo.");
        }
      }

      if($lock['photo_big'] != null)
      
      if($lock['photo_big'] != '' && file_exists(IMAGE_DIR.$lock['photo_big'])){
        if (!unlink(IMAGE_DIR.$lock['photo_big'])){
          $this->set_error("Can't delete photo.");
        }
      }
    }
    $sql = "delete from `".DB_PREF."mc_text_photos_photo_gallery_photo` where photo_gallery = '".(int)$id."' ";
    $rs = mysql_query($sql);
    if(!$rs)
    trigger_error($sql." ".mysql_error());


    $sql = "delete from `".DB_PREF."content_element_to_modules` where module_id = '".(int)$id."'  and group_key = '".mysql_real_escape_string(GROUP_KEY)."' and module_key = '".mysql_real_escape_string(MODULE_KEY)."'";
    if (!mysql_query($sql))
    $this->set_error("Can't delete element to module association ".$sql);
    else{
      $sql = "delete from `".DB_PREF."mc_text_photos_photo_gallery` where id = '".(int)$id."' ";
      if (!mysql_query($sql))
      $this->set_error("Can't delete module ".$sql);
    }
    return;
  }

   


  function make_html($id){
    global $parameters;
    global $db_module;

    global $site;
    $site->requireTemplate('standard/content_management/widgets/'.GROUP_KEY.'/'.MODULE_KEY.'/template.php');

    $layout = $this->getLayout($id);
     
    $sql = "select title, photo, photo_big from `".DB_PREF."mc_text_photos_photo_gallery_photo`"
    ." WHERE photo_gallery = '".(int)$id."' ORDER BY row_number";
    $rs = mysql_query($sql);
    $answer = '';
    if ($rs){
      $photos = array();
      while ($lock = mysql_fetch_assoc($rs)){
        $lock['photo'] = BASE_URL.IMAGE_DIR.$lock['photo'];
        $lock['photo_big'] = BASE_URL.IMAGE_DIR.$lock['photo_big'];
        $photos[] = $lock;
        $answer = Template::generateHtml($photos, $layout);
      }
    }else
    $this->set_error("Can't get photos to create HTML ".$sql);

    return $answer;
  }

  function manager_preview(){
    global $site;
    $site->requireTemplate('standard/content_management/widgets/'.GROUP_KEY.'/'.MODULE_KEY.'/template.php');

    $photos = array();
    if(isset($_REQUEST['photo_number'])){
      foreach($_REQUEST['photo_number'] as $key => $number){
        $photos[] = array('title'=> $_REQUEST['title'.$number], 'photo'=> $_REQUEST['photo'.$number], 'photo_big' => $_REQUEST['photo_big'.$number]);
      }
    }
    return Template::generateHtml($photos, $_REQUEST['layout']);
  }

  function set_error($error){
    global $globalWorker;
    $globalWorker->set_error($error);
  }
}

