<?php
/**
 * @package	ImpressPages
 * @copyright	Copyright (C) 2011 ImpressPages LTD.
 * @license	GNU/GPL, see ip_license.html
 */
namespace Modules\standard\content_management;  

if (!defined('CMS')) exit; 

global $globalWorker; 


require_once (__DIR__.'/db.php');
require_once (BASE_DIR.LIBRARY_DIR.'php/file/upload_file.php');
require_once (BASE_DIR.LIBRARY_DIR.'php/file/upload_image.php');
require_once (__DIR__.'/widgets/widget.php');

$tmpModules = Db::menuModules();

foreach($tmpModules as $groupKey => $group)
  foreach ($group as $moduleKey => $module) {
    require_once (__DIR__.'/widgets/'.$module['group_name'].'/'.$module['module_name'].'/module.php');
  }



require_once (BASE_DIR.LIBRARY_DIR.'php/file/upload_image.php');
require_once (BASE_DIR.LIBRARY_DIR.'php/file/upload_file.php');
require_once (BASE_DIR.LIBRARY_DIR.'php/text/html2text.php');


class BackendWorker {
  var $notes;
  var $errors;
  var $variables;
  function __construct() {

    $this->notes = array();
    $this->errors = array();

    $this->variables = array();
  }


  function work() {



    if (isset($_REQUEST['action']))
      switch($_REQUEST['action']) {
        case "check_parameters":
          $this->check_parameters();
          break;
        case "make_preview":
          $this->make_preview();
          break;
        case "save_page":
          $this->save_page();
          break;
        case "upload_tmp_image":
          $this->upload_tmp_image();
          break;
        case "upload_tmp_file":
          $this->upload_tmp_file();
          break;
        case "upload_tmp_video":
          $this->upload_tmp_video();
          break;
        case "menu_new_page":
          $this->menu_new_page();
          break;
        case "menu_rename_page":
          $this->menu_rename_page();
          break;
        case "menu_new_sub_page":
          $this->menu_new_sub_page();
          break;
        case "menu_show":
          $this->menu_show();
          break;
        case "menu_hide":
          $this->menu_hide();
          break;
        case "menu_delete":
          $this->menu_delete();
          break;
        case "menu_move":
          $this->menu_move();
          break;
        case "manage_element":
          $this->manage_element();
          break;
        case "no_action":
          $this->manage_element();
          $this->javascript_answer();
          break;
        default:
          $this->set_error("no_action");
          $this->javascript_answer();
          break;
      }
  }

  function check_parameters() {
    $answer = '';
    $error = false;
    if(isset($_POST['date'])) {
      if(strtotime($_POST['date']) === false) {
        $error = true;
        $answer .= "document.getElementById('f_main_fields_created_on_error').style.display = 'block';";
      } else {
        $answer .= "document.getElementById('f_main_fields_created_on_error').style.display = 'none';";
      }
    } else {
      $answer .= "document.getElementById('f_main_fields_created_on_error').style.display = 'none';";
    }

    if(isset($_POST['type']) && $_POST['type'] == 'redirect' && $_POST['redirect_url'] == '') {
      $error = true;
      $answer .= "document.getElementById('f_main_fields_redirect_error').style.display = 'block';";
    } else {
      $answer .= "document.getElementById('f_main_fields_redirect_error').style.display = 'none';";
    }

    if (!$error) {
      $answer .= "
       f_main_fields_popup_save_process();
       ";
    }
    echo $answer;
  }

  function save_page() {
    $this->set_main_fields();

    if(isset($_REQUEST['paragraphs']) && is_array($_REQUEST['paragraphs'])) {
      foreach($_REQUEST['paragraphs'] as $paragraph_key => $paragraph) {
        if(isset($paragraph['action'])) {
          switch ($paragraph['action']) {
            case 'new_module':
              $this->new_module($paragraph);
              break;
            case 'update_module':
              $this->update_module($paragraph);
              break;
            case 'delete_module':
              $this->delete_module($paragraph);
              break;
          }
        }
      }
    }
    $this->make_html();
    $this->javascript_answer();
  }

  function make_html() {
    global $site;
    $inited_modules = array();
    $sql = "
        select etm.module_key, etm.module_id, g.name as 'group_key' 
        from `".DB_PREF."content_element_to_modules` etm, `".DB_PREF."content_module_group` g, `".DB_PREF."content_module` m 
        where 
        etm.module_key = m.name and g.id = m.group_id and
        etm.element_id = '".$_REQUEST['id']."' and etm.visible 
        order by etm.row_number";
    $rs = mysql_query($sql);
    $answer = '';
    $cached_html = '';
    $dynamic_modules = array();
    if ($rs) {
      while ($lock = mysql_fetch_assoc($rs)) {
        if ($lock) {
          eval (' $new_module = new \\Modules\\standard\\content_management\\Widgets\\'.$lock['group_key'].'\\'.$lock['module_key'].'\\Module(); ');

          if(!isset($initedmodules[$lock['module_key']])) {
            if (file_exists(BASE_DIR.MODULE_DIR.'standard/content_management/widgets/'.$lock['group_key'].'/'.$lock['module_key'].'/template.php')) {
              $site->requireTemplate('standard/content_management/widgets/'.$lock['group_key'].'/'.$lock['module_key'].'/template.php');
              if (class_exists('\\Modules\\standard\\content_management\\Widgets\\'.$lock['group_key'].'\\'.$lock['module_key'].'\\Template')){
                if (method_exists ('\\Modules\\standard\\content_management\\Widgets\\'.$lock['group_key'].'\\'.$lock['module_key'].'\\Template', "initHtml")) {
                  eval('$answer .= \\Modules\\standard\\content_management\\Widgets\\'.$lock['group_key'].'\\'.$lock['module_key'].'\\Template::initHtml();');
                  eval('$cached_html .= \\Modules\\standard\\content_management\\Widgets\\'.$lock['group_key'].'\\'.$lock['module_key'].'\\Template::initHtml();');
                  $initedmodules[$lock['module_key']] = 1;
                }
              }
            
            }
          }          
          
          
          if ($new_module->is_dynamic()) {
            $dynamic_modules[] = array("module_group" => $lock['group_key'], "module_name" => $lock['module_key'], "id" => $lock['module_id']);
            $answer .= "<dynamic_module/>";
            $cached_html .= $new_module->make_html($lock['module_id']);
          }else {
            $tmpHtml = $new_module->make_html($lock['module_id']);
            $answer .= $tmpHtml;
            $cached_html .= $tmpHtml;
          }
        }



      }

      $html2text = new \Library\Php\Text\Html2Text();
      $html2text->set_html($cached_html);
      $cached_text = $html2text->get_text();
      $sql = "update `".DB_PREF."content_element` set last_modified = CURRENT_TIMESTAMP, cached_text = '".mysql_real_escape_string($cached_text)."', cached_html='".mysql_real_escape_string($cached_html)."', dynamic_modules = '".mysql_real_escape_string(serialize($dynamic_modules))."', html = '".mysql_real_escape_string($answer)."' where id = '".$_REQUEST['id']."'";
      $rs = mysql_query($sql);
      if (!$rs)
        $this->set_error("Can't update HTML ".$sql);
    }
    else
      $this->set_error("Can't make HTML ".$sql." ".mysql_error());
  }

  function make_preview() {
    if (isset($_REQUEST['module_key']) && ($_REQUEST['module_key'] != null) && isset($_REQUEST['group_key']) && ($_REQUEST['group_key'] != null)) {
      $menuModule = Db::getMenuModModule(null, $_REQUEST['group_key'], $_REQUEST['module_key']);  
      if ($menuModule) {
          eval (' $new_module = new \\Modules\\standard\\content_management\\Widgets\\'.$menuModule['g_name'].'\\'.$menuModule['m_name'].'\\Module(); ');
          $answer = $new_module->manager_preview();
          $this->set_variable("html", $answer);
          $this->set_variable("collection_number", $_REQUEST['collection_number']);
      } else {
          $this->set_error("Bad parameters");
      }  
    }else $this->set_error("Bad parameters");
    $this->javascript_answer();
  }


  function new_module($values) {
    if (isset($values['module_key']) && ($values['module_key'] != null) && isset($values['group_key']) && ($values['group_key'] != null)) {
      $menuModule = Db::getMenuModModule(null, $values['group_key'], $values['module_key']);  
      if ($menuModule) {
          eval (' $new_module = new \\Modules\\standard\\content_management\\Widgets\\'.$menuModule['g_name'].'\\'.$menuModule['m_name'].'\\Module(); ');
          $answer = $new_module->create_new_instance($values);
          if($answer)
            $this->set_error($answer);
      } else {
          $this->set_error("Bad parameters");
      }
    }else $this->set_error("Bad parameters");
  }

  function update_module($values) {
    if (isset($values['module_key']) && ($values['module_key'] != null) && isset($values['group_key']) && ($values['group_key'] != null)) {
      $menuModule = Db::getMenuModModule(null, $values['group_key'], $values['module_key']);  
      if ($menuModule) {
          eval (' $new_module = new \\Modules\\standard\\content_management\\Widgets\\'.$menuModule['g_name'].'\\'.$menuModule['m_name'].'\\Module(); ');
          $answer = $new_module->update($values);
          if ($answer[0])
            foreach($answer[0] as $key => $value)
              $this->set_error($value);
          if ($answer[1])
            foreach($answer[1] as $key => $value)
              $this->set_note($value);
      } else {
          $this->set_error("Bad parameters");
      }
    }else $this->set_error("Bad parameters");
  }

  function delete_module($values) {
    if (isset($values['module_key']) && ($values['module_key'] != null) && isset($values['group_key']) && ($values['group_key'] != null)) {
        
      $menuModule = Db::getMenuModModule(null, $values['group_key'], $values['module_key']);  
      if ($menuModule) {
          eval (' $new_module = new \\Modules\\standard\\content_management\\Widgets\\'.$menuModule['g_name'].'\\'.$menuModule['m_name'].'\\Module(); ');
              $answer = $new_module->delete($values);
          if ($answer[0])
            foreach($answer[0] as $key => $value)
              $this->set_error($value);
          if ($answer[1])
            foreach($answer[1] as $key => $value)
              $this->set_note($value);
      } else {
          $this->set_error("Bad parameters");
      }
    }else $this->set_error("Bad parameters");
  }

  function upload_tmp_image() {
    $upload_image = new \Library\Php\File\UploadImage();
    if(isset($_POST['photo_width']) && $_POST['photo_width'] != null) {
      foreach($_POST['photo_width'] as $key => $parameter) {
        $answer = $upload_image->upload('new_photo',$_POST['photo_width'][$key], $_POST['photo_height'][$key], TMP_IMAGE_DIR, $_POST['photo_method'][$key], $_POST['photo_forced'][$key], $_POST['photo_quality'][$key]);
        if($answer == UPLOAD_ERR_OK) {
          $this->set_variable("name".$key, $upload_image->fileName);
        }
        else {
          $this->set_error($answer);
        }
      }
    }
    $this->javascript_answer();
  }

  function upload_tmp_file() {
    global $errors;
    $upload_file = new \Library\Php\File\UploadFile();
    $answer = $upload_file->upload('new_photo', TMP_FILE_DIR);
    if($answer == UPLOAD_ERR_OK) {
      $this->set_variable("name0", $upload_file->fileName);
    }else {
      $this->set_error($answer);
    }
    $this->javascript_answer();
  }



  function upload_tmp_video() {
    global $errors;
    $upload_file = new \Library\Php\File\UploadFile();
    $upload_file->allowOnly(array('flv'));
    $answer = $upload_file->upload('new_photo', TMP_VIDEO_DIR);
    if($answer == UPLOAD_ERR_OK) {
      $this->set_variable("name0", $upload_file->fileName);
    }else {
      $this->set_error($answer);
    }
    $this->javascript_answer();
  }


  function menu_new_page() {
    $parent = $_REQUEST['parent'];
    $title = $_REQUEST['title'];
    $index = $_REQUEST['index'];
    $node = $_REQUEST['node'];
    $rss = $_REQUEST['rss'];
    $visible = $_REQUEST['visible'];



    $new_id = Db::insertMenuElement($parent, $index, $title, $title, $title, $title, $title, $rss, $visible);

    $this->set_variable("menu_max_node_id", $new_id);
    $this->set_variable("menu_title", $title);
    $this->set_variable("menu_parent", $parent);
    $this->set_variable("menu_node", $node);

    global $parametersMod;
    if($parametersMod->getValue('standard', 'menu_management', 'options', 'hide_new_pages'))
      $visible = '0';
    else
      $visible = '1';

    $this->set_variable("visible", $visible);
    $this->javascript_answer();
  }

  function menu_rename_page() {
    $title = $_REQUEST['title'];
    $node = $_REQUEST['node'];

    Db::renameContentElement($node, $title);

    $this->set_variable("node_name", htmlspecialchars($title));
    $this->javascript_answer();
  }


  function menu_new_sub_page() {
    $parent = $_REQUEST['node'];
    $title = $_REQUEST['title'];
    $rss = $_REQUEST['rss'];

    $new_id = Db::insertMenuElement($parent, sizeof(Db::menuElementChildren($parent)), $title);


    $this->set_variable("menu_max_node_id", $new_id);
    $this->set_variable("menu_title", $title);
    $this->set_variable("menu_parent", $parent);

    global $parametersMod;
    if($parametersMod->getValue('standard', 'menu_management', 'options', 'hide_new_pages'))
      $visible = '0';
    else
      $visible = '1';

    $this->set_variable("visible", $visible);

    $this->javascript_answer();
  }

  function menu_show() {
    Db::showMenuElement($_REQUEST['node']);
    $this->javascript_answer();
  }

  function menu_hide() {
    Db::hideMenuElement($_REQUEST['node']);
    $this->javascript_answer();
  }

  function menu_delete() {
    $this->remove_element($_REQUEST['node']);
    Db::changeMenuElementRowNumbers($_REQUEST['parent'], $_REQUEST['index'], -1, '>');
    $this->javascript_answer();
  }


  function menu_move() {
    global $site;
    $node = $_REQUEST['node'];
    $old_parent = $_REQUEST['old_parent'];
    $new_parent = $_REQUEST['new_parent'];
    $new_index = $_REQUEST['new_index'];
    $old_index = $_REQUEST['old_index'];

    //report url cange
    $elementZone = $site->getZone($_POST['zone_name']);
    $element = $elementZone->getElement($_POST['node']);
    $oldUrl = $element->getLink(true);
    //report url change

    Db::changeMenuElementRowNumbers($old_parent, $old_index, -1, '>');

    if($old_parent == $new_parent && $new_index > $old_index)
      $new_index--;

    Db::changeMenuElementRowNumbers($new_parent, $new_index, 1, '>=');

    Db::moveMenuElement($node, $new_parent, $new_index);

    if(!Db::correctRowNumbers($old_parent)) {
      trigger_error("Incorrect row numbers on parent ".$old_parent);
    }

    if($new_parent != $old_parent && !Db::correctRowNumbers($new_parent)) {
      trigger_error("Incorrect row numbers on parent ".$old_parent);
    }


    //report url change
    $elementZone = $site->getZone($_POST['zone_name']);
    $element = $elementZone->getElement($_POST['node']);
    $newUrl = $element->getLink(true);
    $site->dispatchEvent('administrator', 'system', 'url_change', array('old_url'=>$oldUrl, 'new_url'=>$newUrl));
    //report url change


    $this->javascript_answer();
  }




  function set_note($note) {
    $this->notes[] = $note;
  }

  function set_error($error) {
    $this->errors[] = $error;
  }

  function set_variable($name, $value) {
    $this->variables[''.$name] = $value;
  }




  function remove_element($id) {
    $children = Db::menuElementChildren($id);
    if($children)
      foreach($children as $key => $lock) {
        $this->remove_element($lock['id']);
      }


    //delete paragraphs
    $paragraphs = Db::menuElementParagraphs($id);
    foreach($paragraphs as $key => $lock) {
      eval(' $tmp_module = new \\Modules\\standard\\content_management\\Widgets\\'.$lock['group_key'].'\\'.$lock['module_key'].'\\Module(); ');
      $answer = $tmp_module->delete_by_id($lock['module_id']);
      if ($answer[0])
        foreach($answer[0] as $key => $value)
          $this->set_error($value);
      if ($answer[1])
        foreach($answer[1] as $key => $value)
          $this->set_error($value);
    }
    //end delete paragraphs

    Db::deleteMenuElement($id);
  }


  function manage_element() {
    global $parametersMod;
    $urls = array();
    $node = $_REQUEST['node'];
    $current = Db::menuElement($node);
    //$urls[] = $current['url'];
    while($current != '' && $current['parent'] != 0 && $current['parent'] != '') {
      $urls[] = $current['url'];
      $current = Db::menuElement($current['parent']);
    }

    $answer = '?cms_action=manage';
    for($i=0; $i<sizeof($urls);$i++)
      $answer = urlencode($urls[$i]).'/'.$answer;

    $urls = Db::urlsByRootMenuElement($current['id']);

    if($parametersMod->getValue('standard', 'languages', 'options', 'multilingual'))
      $answer = BASE_URL.urlencode($urls['lang_url']).'/'.urlencode($urls['zone_url']).'/'.$answer;
    else
      $answer = BASE_URL.urlencode($urls['zone_url']).'/'.$answer;

    $this->set_variable('link', $answer);

    $this->javascript_answer();
  }



  function javascript_answer() {
    $answer = "<html>
    <head>
  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=".CHARSET."\" />
    </head>
    <body>
      <script type=\"text/javascript\">
      var notes = new Array();  var errors = new Array();  var variables = new Array(); ";
    foreach($this->notes as $key => $note) {
      $answer .= "notes.push('".htmlspecialchars(str_replace('script',"scr' + 'ipt",str_replace("\r", "", str_replace("\n", "\\n' + \n '", str_replace("'", "\\'", str_replace("\\", "\\\\",$note))))))."');";
    }
    foreach($this->errors as $key => $error) {
      $answer .= "errors.push('".htmlspecialchars(str_replace('script',"scr' + 'ipt",str_replace("\r", "", str_replace("\n", "\\n' + \n '", str_replace("'", "\\'", $error)))))."');";

    }

    /* foreach($variables as $key => $value){
      $answer .= " var $key = '$value'; ";
    }*/

    foreach($this->variables as $key => $value) {
      $answer .= "variables.push('".str_replace('script',"scr' + 'ipt",str_replace("\r", "", str_replace("\n", "\\n' + \n '", str_replace("'", "\\'", str_replace("\\", "\\\\",$value)))))."');";
    }


    if (isset($_REQUEST['answer_function']) && $_REQUEST['answer_function'] != null)
      $answer .= " var script =  '".$_REQUEST['answer_function']."(notes, errors, variables);'";


    $answer .=  "
    </script>
    </body></html>";

    echo $answer;
  }



  function set_main_fields() {
    global $parametersMod;
    global $site;
    $url = $_REQUEST['url'];
    $url = str_replace('/', '-', $url);
    if($url == ''){
      $url = 'page';
    }

    //report about changed URL
      $elementZone = $site->getZone($_REQUEST['zone_name']);
      $element = $elementZone->getElement($_REQUEST['id']);
      $oldUrl = $element->getLink(true);
    //report about changed URL

    $url = $this->makeUrl($url, $_REQUEST['id']);

    if($_REQUEST['rss'] == 1)
      $rss = ' 1 ';
    else
      $rss = ' 0 ';

    if($_REQUEST['visible'] == 1)
      $visible = ' 1 ';
    else
      $visible = ' 0 ';

    $sql = "update `".DB_PREF."content_element`
    set 
    
    page_title = '".mysql_real_escape_string($_REQUEST['page_page_title'])."', 
    button_title = '".mysql_real_escape_string($_REQUEST['page_button_title'])."', 
    keywords = '".mysql_real_escape_string($_REQUEST['keywords'])."', 
    description = '".mysql_real_escape_string($_REQUEST['description'])."', 
    url = '".mysql_real_escape_string($url)."', 
    redirect_url = '".mysql_real_escape_string($_REQUEST['redirect_url'])."', 
    type = '".mysql_real_escape_string($_REQUEST['type'])."', 
    rss = ".$rss.", 
    visible = ".$visible.",
    created_on = '".date("Y-m-d", strtotime($_REQUEST['created_on']))."'
    where id = '".mysql_real_escape_string($_REQUEST['id'])."'";
    $rs = mysql_query($sql);

    if (!$rs){
      $this->set_error("Can't update page title ".$sql);
    }


    //report about changed URL
      $elementZone = $site->getZone($_REQUEST['zone_name']);
      $element = $elementZone->getElement($_REQUEST['id']);
      $newUrl = $element->getLink(true);
      if($newUrl != $oldUrl){
        $site->dispatchEvent('administrator', 'system', 'url_change', array('old_url'=>$oldUrl, 'new_url'=>$newUrl));
      }
    //report about changed URL

    if(isset($_POST['f_main_parameter'])) {
      foreach($_POST['f_main_parameter'] as $key => $parId) {
        $parameter = \Db::getParameterById($parId);
        $parameter_group = \Db::getParameterGroupById($parameter['group_id']);
        $module = \Db::getModule($parameter_group['module_id']);
        if(isset($_POST['f_main_parameter_language'][$key]) && $_POST['f_main_parameter_language'][$key] != '') {
          $parametersMod->setValue($module['g_name'], $module['m_name'], $parameter_group['name'], $parameter['name'], $_POST['f_main_parameter_value'][$key], $_POST['f_main_parameter_language'][$key]);
        }else {
          $parametersMod->setValue($module['g_name'], $module['m_name'], $parameter_group['name'], $parameter['name'], $_POST['f_main_parameter_value'][$key]);
        }
      }
    }


  }


  function availableUrl($url, $allowedId) {
    if($allowedId)
      $sql = "select url from `".DB_PREF."content_element` where url = '".mysql_real_escape_string($url)."' and id <> '".$allowedId."'";
    else
      $sql = "select url from `".DB_PREF."content_element` where url = '".mysql_real_escape_string($url)."' ";

    $rs = mysql_query($sql);
    if(!$rs)
      trigger_error("Available url check ".$sql." ".mysql_error());

    if(mysql_num_rows($rs) > 0)
      return false;
    else
      return true;
  }

  function makeUrl($url, $allowedId = null) {
    $url = str_replace("/", "-", $url);

    if($this->availableUrl($url, $allowedId))
      return $url;

    $i = 1;
    while(!$this->availableUrl($url.'-'.$i, $allowedId)) {
      $i++;
    }

    return $url.'-'.$i;
  }

}







