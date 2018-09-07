<?php
/*****************************************************
 * Use plugin form/form_v1 instead.
 ****************************************************/

/**
 * Plugin to render and handle forms.
 */
class PluginWfForm_v2{
  private $i18n = null;
  function __construct($buto = false) {
    if($buto){
      wfPlugin::includeonce('wf/array');
      wfPlugin::includeonce('i18n/translate_v1');
      $this->i18n = new PluginI18nTranslate_v1();
    }
  }
  /**
   * Primary key (only one).
   */
  private static function getSchemaFieldPrimary($field){
    $primary_key = null;
    $primary_type = null;
    foreach ($field as $key => $value) {
      $item = new PluginWfArray($value);
      if($item->get('primary_key')){
        if($primary_key){
          exit('PluginWfForm_v2 says: Table should only have one primary key.');
        }else{
          $primary_key = $key;
          if(strstr($item->get('type'), 'varchar(')){
            $primary_type = 's';
          }elseif(strstr($item->get('type'), 'int(')){
            $primary_type = 'i';
          }
        }
      }
    }
    if(!$primary_key){
      exit('PluginWfForm_v2 says: Table has no primary key.');
    }
    return new PluginWfArray(array('primary_key'  => $primary_key, 'primary_type' => $primary_type));
  }
  private static function setFormItemsDefaultFromDb($form){
    /**
     * Get items via schema.
     */
    $field = PluginWfForm_v2::getSchema($form);
    /**
     * Primary key (only one).
     */
    $primary = PluginWfForm_v2::getSchemaFieldPrimary($field);
    $primary_key = $primary->get('primary_key');
    $primary_type = $primary->get('primary_type');
    /**
     * Create select sql.
     */
    $sql = 'select ';
    foreach ($field as $key => $value) {
      $sql .= "$key, ";
    }
    $sql = substr($sql, 0, strlen($sql)-2);
    $sql .= " from ".$form->get('table')." where $primary_key=?;";
    $select = array();
    foreach ($field as $key => $value) {
      $select[] = $key;
    }
    $params = array();
    $params[$primary_key] = array('type' => $primary_type, 'value' => wfRequest::get($primary_key));
    $mysql_data = array('sql' => $sql, 'select' => $select, 'params' => $params);
    /**
     * Get data.
     */
    if(wfRequest::get($primary_key)){
      wfPlugin::includeonce('wf/mysql');
      $mysql = new PluginWfMysql();
      $mysql->open($form->get('mysql'));
      $mysql->execute($mysql_data);
      $rs = new PluginWfArray($mysql->getStmtAsArray());
      if($rs->get('0')){
        foreach ($rs->get('0') as $key => $value) {
          if($form->get("items/$key")){
            $form->set("items/$key/default", $value);
          }
        }
      }
    }
    return $form;
  }
  /**
   * <p>Render a form.</p> 
   * <p>Consider to add data in separate yml file because you need to pic it up again when handle posting values. Use widget to handle post request if necessary.</p> 
   * <p>'yml:/theme/[theme]/form/my_form.yml'</p>
   */
  public static function widget_render($data){
    /**
     * Handle data param.
     */
    if(wfArray::isKey($data, 'data')){
      if(!is_array(wfArray::get($data, 'data'))){
        /**
         * If not an array it must be path to file.
         */
        $filename = wfArray::get($GLOBALS, 'sys/app_dir').wfArray::get($data, 'data');
        if(file_exists($filename)){
          $data['data'] = sfYaml::load($filename);
        }else{
          throw new Exception("Could not find file $filename.");
        }
      }
    }else{
      throw new Exception("Param data is not set.");
    }
    /**
     * Create form and include dependencies.
     */
    wfPlugin::includeonce('wf/array');
    $form = new PluginWfArray($data['data']);
    $data_obj = new PluginWfArray($data);
    $scripts = array();
    /**
     * Get from db via schema.
     */
    if($form->get('schema') && $form->get('table') && $form->get('mysql')){
      $form = PluginWfForm_v2::setFormItemsDefaultFromDb($form);
    }
    /**
     * Call a render method if exist to fill the form.
     */
    if($form->get('render/plugin') && $form->get('render/method')){
      $form = (PluginWfForm_v2::runCaptureMethod($form->get('render/plugin'), $form->get('render/method'), $form));
      //$data['data'] = $form->get();
    }
    /**
     * Default values.
     */
    $default = array(
        'submit_value' => 'Send',
        'submit_class' => 'btn btn-primary',
        'id' => str_replace('.', '', uniqid(mt_rand(), true)),
        'script' => null,
        'ajax' => false,
        'url' => '/doc/_',
        'items' => array()
        );
    /**
     * Merge defaults with widget data.
     */
    $default = array_merge($default, $form->get());
    $default['url'] = wfSettings::replaceClass($default['url']);
    /**
     * Buttons.
     */
    $buttons = array();
    if($default['ajax']) {
      if(!$data_obj->get('data/ajax_element')){
        $onclick = "if(typeof PluginBootstrapAlertwait == 'object'){PluginBootstrapAlertwait.run(function(){  $.post('".$default['url']."', $('#".$default['id']."').serialize()).done(function(data) { PluginWfCallbackjson.call( data ); })     }); return false;}else{ $.post('".$default['url']."', $('#".$default['id']."').serialize()).done(function(data) { PluginWfCallbackjson.call( data ); }); return false; }";
      }else{
        $onclick = "if(typeof PluginBootstrapAlertwait == 'object'){ PluginBootstrapAlertwait.run(function() {PluginWfCallbackjson.setElement('".$data_obj->get('data/ajax_element')."', '".$default['url']."', '".$default['id']."' )     }); return false; }else{ PluginWfCallbackjson.setElement('".$data_obj->get('data/ajax_element')."', '".$default['url']."', '".$default['id']."' ); return false; }";
      }
      $buttons[] = wfDocument::createHtmlElement('a', $default['submit_value'], array('class' => $default['submit_class'], 'onclick' => $onclick, 'id' => $default['id'].'_save'));
    }  else {
      $buttons[] = wfDocument::createHtmlElement('input', null, array('type' => 'submit', 'value' => $default['submit_value'], 'class' => $default['submit_class']));
    }
    if($form->get('buttons')){
      foreach ($form->get('buttons') as $key => $value) {
        $buttons[] = wfDocument::createHtmlElement($value['type'], $value['innerHTML'], $value['attribute']);
      }
    }
    /**
     * Elements above.
     */
    $form_element = array();
    if($form->get('elements_above')){
      $form_element[] = wfDocument::createHtmlElement('div', $form->get('elements_above'), array('id' => $default['id'].'_elements_above'));
    }
    /**
     * Items.
     */
    $form_row = array();
    if(sizeof($default['items']) > 0){
      foreach ($default['items'] as $key => $value) {
        $form_row[] = PluginWfForm_v2::getRow($key, $value, $default);
      }
    }else{
      exit('No items or schema/table/mysql is set.');
    }
    $form_element[] = wfDocument::createHtmlElement('div', $form_row, array('id' => $default['id'].'_controls'));
    /**
     * Layout.
     */
    if($form->get('layout')){
      $form_element[] = wfDocument::createHtmlElement('div', $form->get('layout'), array('id' => $default['id'].'_layout'));
      $form_element[] = wfDocument::createHtmlElement('script', "document.getElementById('".$default['id']."_controls').style.display='none';");
      $form_element[] = wfDocument::createHtmlElement('script', "PluginWfForm_v2.renderLayout({id: '".$default['id']."'});");
    }
    /**
     * Elements below.
     */
    if($form->get('elements_below')){
      $form_element[] = wfDocument::createHtmlElement('div', $form->get('elements_below'), array('id' => $default['id'].'_elements_below'));
    }
    /**
     * Buttons.
     */
    $form_element[] = wfDocument::createHtmlElement('div', $buttons, array('class' => 'wf_form_row'));
    /**
     * Attribute.
     */
    $form_attribute = array('id' => $default['id'], 'method' => 'post', 'role' => 'form');
    if(!$default['ajax']){
      $form_attribute['action'] = $default['url'];
    }
    $form_render = wfDocument::createHtmlElement('form', $form_element, $form_attribute);
    /**
     * Move buttons to footer if Bootstrap modal.
     */
    $script_move_btn = wfDocument::createHtmlElement('script', "if(typeof PluginWfBootstrapjs == 'object'){PluginWfBootstrapjs.moveModalButtons('".$form->get('id')."');}");
    /**
     * Render.
     */
    wfDocument::renderElement(array($form_render, $script_move_btn));
    wfDocument::renderElement($scripts);
  }
  /**
   * Get fields via schema.
   */
  public static function getSchema($form){
    $schema = new PluginWfYml(wfArray::get($GLOBALS, 'sys/app_dir').$form->get('schema'));
    $field = new PluginWfArray($schema->get('tables/'.$form->get('table')));
    $extra = new PluginWfArray($schema->get('extra'));
    if($extra->get('field')){
      foreach ($extra->get('field') as $key => $value) {
        $field->set("field/$key", $value);
      }
    }
    return $field->get('field');
  }
  /**
   * 
   * @param type $key
   * @param type $value
   * @param type $default
   * @return type
   */
  private static function getRow($key, $value, $default){
    $scripts = array();
    $default_value = array(
        'label' => $key,
        'default' => '',
        'element_id' => $default['id'].'_'.$key,
        'name' => $key,
        'readonly' => null,
        'type' => 'varchar',
        'checked' => null,
        'mandatory' => null,
        'option' => null,
        'wrap' => null,
        'class' => 'form-control',
        'style' => null,
        'placeholder' => null,
        'html' => false
            );
    $default_value = array_merge($default_value, $value);
    $type = null;
    $innerHTML = null;
    $attribute = array('name' => $default_value['name'], 'id' => $default_value['element_id'], 'class' => $default_value['class'], 'style' => $default_value['style']);
    switch ($default_value['type']) {
      case 'checkbox':
        $type = 'input';
        $attribute['type'] = 'checkbox';
        if($default_value['checked'] || $default_value['default']=='1'){
          $attribute['checked'] = 'checked';
        }
        break;
      case 'text':
        $type = 'textarea';
        $attribute['wrap'] = $default_value['wrap'];
        $innerHTML = $default_value['default'];
        /**
         * HTML editor via Nic Editor.
         */
        if($default_value['html']){
          wfPlugin::includeonce('wysiwyg/nicedit');
          $nicedit = new PluginWysiwygNicedit();
          $scripts[] = $nicedit->getTextareaScript($default_value['element_id']);
        }
        break;
      case 'password':
        $type = 'input';
        $attribute['type'] = 'password';
        $attribute['value'] = $default_value['default'];
        break;
      case 'map':
        $type = 'input';
        $attribute['type'] = 'text';
        $attribute['value'] = htmlentities($default_value['default']);
        $attribute['style'] = 'display:none';
        $attribute['onchange'] = "if(this.value.length){document.getElementById('span_map_icon_".$default_value['element_id']."').style.display='';}else{document.getElementById('span_map_icon_".$default_value['element_id']."').style.display='none';}";
        break;
        break;
      case 'varchar':
      case 'date':
        if($default_value['type']=='date'){
          $scripts[] = wfDocument::createHtmlElement('script', "if($('#".$default['id']."_$key').datepicker){this.datepicker = $('#".$default['id']."_$key').datepicker({ format: 'yyyy-mm-dd', weekStart: 1, daysOfWeekHighlighted: '0,6', autoclose: true, todayHighlight: true, forceParse: false  });}");
        }
        if(!$default_value['option']){
          $type = 'input';
          $attribute['type'] = 'text';
          $attribute['value'] = htmlentities($default_value['default']);
          $attribute['placeholder'] = $default_value['placeholder'];
        }else{
          /**
           * Set data from yml file if 'yml:_pat_to_yml_file_'.
           */
          if(!is_array($default_value['option'])){
            $default_value['option'] = wfSettings::getSettingsFromYmlString($default_value['option']);
          }
          if(!is_array($default_value['option'])){
            $default_value['option'] = wfSettings::getSettingsFromMethod($default_value['option']);
          }
          /**
           * 
           */
          $type = 'select';
          $option = array();
          foreach ($default_value['option'] as $key2 => $value2) {
            $temp = array();
            $temp['value'] = $key2;
            if((string)$default_value['default']===(string)$key2){
              $temp['selected'] = 'true';
            }
            $option[] = wfDocument::createHtmlElement('option', $value2, $temp);
          }
          $innerHTML = $option;
        }
        break;
      case 'hidden':
        $type = 'input';
        $attribute['type'] = 'hidden';
        $attribute['value'] = $default_value['default'];
        break;
      case 'div':
        $type = 'div';
        break;
      default:
        break;
    }
    if($type){
      if($type=='div'){
        return $value;
      }else{
        $temp = array();
        if(wfArray::get($attribute, 'type') != 'hidden'){
          $temp['label'] = PluginWfForm_v2::getLabel($default_value);
          if($default_value['mandatory']){
            $temp['mandatory'] = wfDocument::createHtmlElement('label', '*', array('id' => 'label_mandatory_'.$default_value['element_id']));
          }
        }
        if($default_value['type'] == 'map'){
          $display = 'none';
          if(strlen($default_value['default'])){
            $display = '';
          }
          $temp['map_icon'] = wfDocument::createHtmlElement('a', array(wfDocument::createHtmlElement('span', null, array('id' => 'span_map_icon_'.$default_value['element_id'], 'class' => 'glyphicon glyphicon-map-marker', 'style' => "display:$display"))), array('onclick' => "PluginWfForm_v2.showMap('".$default_value['element_id']."');", 'class' => 'form-control', 'style' => "text-align:right"));
        }
        /**
         * Add Bootstrap glyphicon.
         */
        if(wfArray::get($value, 'info/text')){
          $data_placement = 'left';
          if(wfArray::get($value, 'info/position')){
            $data_placement = wfArray::get($value, 'info/position');
          }
          $temp['glyphicon_info'] = wfDocument::createHtmlElement('span', null, array(
              'id' => 'info_'.$default_value['element_id'],
              'title' => $default_value['label'], 
              'class' => 'wf_form_v2 glyphicon glyphicon-info-sign', 
              'style' => 'float:right;cursor:pointer;',
              'data-toggle' => 'popover',
              'data-trigger' => 'click',
              'data-html' => true,
              'data-placement' => $data_placement,
              'data-content' => wfArray::get($value, 'info/text'),
              'onclick' => "$('.wf_form_v2').popover('hide');"
              ));
          $temp['script'] = wfDocument::createHtmlElement('script', " $(function () {  $('[data-toggle=\"popover\"]').popover()}) ");
        }
        $temp['input'] = wfDocument::createHtmlElement($type, $innerHTML, $attribute);
        if($scripts){
          foreach ($scripts as $key2 => $value2) {
            $temp["script$key2"] = $value2;
          }
        }
        return wfDocument::createHtmlElement('div', $temp, array(
                'id' => 'div_'.$default['id'].'_'.$key, 
                'class' => 'form-group '.wfArray::get($value, 'container_class'), 
                'style' => wfArray::get($value, 'container_style')
                ), array('class' => 'wf_form_row'));
      }
    }else{
      return null;
    }
  }
  private static function getLabel($default_value){
    return wfDocument::createHtmlElement('label', $default_value['label'], array('for' => $default_value['element_id'], 'id' => 'label_'.$default_value['element_id']));
  }
  /**
   * Capture post from form via ajax.
   * @param type $data
   */
  public static function widget_capture($data){
    wfPlugin::includeonce('wf/array');
    $json = new PluginWfArray();
    $form = new PluginWfArray($data['data']);
    /**
     * Call a before validation method.
     */
    wfPlugin::includeonce('wf/array');
    $form = new PluginWfArray($data['data']);
    $form->set(null, PluginWfForm_v2::bind($form->get()));
    if($form->get('validation_before/plugin') && $form->get('validation_before/method')){
      $form = (PluginWfForm_v2::runCaptureMethod($form->get('validation_before/plugin'), $form->get('validation_before/method'), $form));
    }
    $form->set(null, PluginWfForm_v2::validate($form->get()));
    $json->set('success', false);
    $json->set('uid', wfCrypt::getUid());
    if($form->get('is_valid')){
      if($form->get('capture/plugin') && $form->get('capture/method')){
        $json->set('script', PluginWfForm_v2::runCaptureMethod($form->get('capture/plugin'), $form->get('capture/method'), $form));
      }else{
        $json->set('script', array("alert(\"Param capture is missing in form data!\");"));
      }
    }else{
      $json->set('script', array("alert(\"".PluginWfForm_v2::getErrors($form->get(), "\\n")."\");"));
    }
    exit(json_encode($json->get()));
  }
  /**
   * Bind request params to form.
   * @param type $form
   * @return boolean
   */
  public static function bind($form, $preserve_default = false){
    foreach ($form['items'] as $key => $value) {
      $str = wfRequest::get($key);
      if($form['items'][$key]['type']=='checkbox'){
        if($str=='on'){$str=true;}
      }
      $form['items'][$key]['post_value'] = $str;
      if(!$preserve_default){
        $form['items'][$key]['default'] = $str;
      }
      /**
       * Set '' to null if type is date to get it to work with wf/mysql.
       */
      if($form['items'][$key]['type']=='date' && $form['items'][$key]['post_value']==''){
        $form['items'][$key]['post_value'] = null;
      }
    }
    return $form;
  }
  /**
   * Bind array where keys matching keys in form.
   */
  public static function setDefaultsFromArray($form, $array){
    foreach ($form['items'] as $key => $value) {
      if(isset($array[$key])){
        $form['items'][$key]['default'] = $array[$key];
      }
    }
    return $form;
  }
  /**
   * Set option from array.
      -
        value: 11
        option: 'Blekinge län'
      -
        value: 19
        option: Dalarna
   */
  public static function setOptionFromArray($form, $item, $array, $add_empty=true){
    $option = PluginWfForm_v2::getOption($array, $add_empty);
    $form->set("items/$item/option", $option);
    return $form;
  }
  /**
   * Format options to be used in forms.
   * @param Array $array Keys must be value and option.
   * @param Boolena $add_empty If begin with an empty option.
   * @return Array
   */
  public static function getOption($array, $add_empty=true){
    $option = array();
    if($add_empty){
      $option[''] = '';
    }
    foreach ($array as $key => $value) {
      $option[$value['value']] = $value['option'];
    }
    return $option;
  }
  /**
   * Validate form.
   * @param type $form
   * @return type
   */
  public static function validate($form){
    /**
     * i18n.
     */
    wfPlugin::includeonce('i18n/translate_v1');
    $i18n = new PluginI18nTranslate_v1();
    //Validate mandatory.
    foreach ($form['items'] as $key => $value) {
      /**
       * If alerade validated skip this field.
       */
      if(isset($form['items'][$key]['is_valid']) && $form['items'][$key]['is_valid']==false){
        continue;
      }
        if(isset($value['mandatory']) && $value['mandatory']){
            if(strlen($value['post_value'])){
                $form['items'][$key]['is_valid'] = true;
            }else{
                $form['items'][$key]['is_valid'] = false;
                //$form['items'][$key]['errors'][] = __('?label is empty.', array('?label' => $form['items'][$key]['label']));
                $form['items'][$key]['errors'][] = $i18n->translateFromTheme('?label is empty.', array('?label' => $i18n->translateFromTheme($form['items'][$key]['label'])));
            }
        }else{
            $form['items'][$key]['is_valid'] = true;
        }
    }
    //Validate email.
    foreach ($form['items'] as $key => $value) {
        if($value['is_valid']){
            if(isset($value['validate_as_email']) && $value['validate_as_email']){
                if (!filter_var($value['post_value'], FILTER_VALIDATE_EMAIL)) {
                    // invalid emailaddress
                    $form['items'][$key]['errors'][] = __('?label is not an email.', array('?label' => $form['items'][$key]['label']));
                    $form['items'][$key]['is_valid'] = false;
                }                
            }
        }
    }
    //Validate php code injection.
    foreach ($form['items'] as $key => $value) {
      if($value['is_valid']){
        if (strstr($value['post_value'], '<?php') || strstr($value['post_value'], '?>')) {
            $form['items'][$key]['errors'][] = __('?label has illegal character.', array('?label' => $form['items'][$key]['label']));
            $form['items'][$key]['is_valid'] = false;
        }                
      }
    }
    // Validator
    foreach ($form['items'] as $key => $value) {
      if(wfArray::get($value, 'validator')){
        foreach (wfArray::get($value, 'validator') as $key2 => $value2) {
          wfPlugin::includeonce($value2['plugin']);
          $obj = wfSettings::getPluginObj($value2['plugin']);
          $method = $value2['method'];
          if(wfArray::get($value2, 'data')){
            $form = $obj->$method($key, $form, wfArray::get($value2, 'data'));
          }else{
            $form = $obj->$method($key, $form);
          }
        }
      }
    }
    //Set form is_valid.
    $form['is_valid'] = true;
    foreach ($form['items'] as $key => $value) {
        if(!$value['is_valid']){
            $form['is_valid'] = false;
            //$form['errors'][] = __('The form does not pass validation.');
            $form['errors'][] = $i18n->translateFromTheme('The form does not pass validation.');
            break;
        }
    }
    return $form;
  }
  /**
   * Bind and validate form.
   * @param type $form
   * @return type
   */      
  public static function bindAndValidate($form){
    $form = self::bind($form);
    $form = self::validate($form);
    return $form;
  }
  /**
   * Set error for a field.
   * @param type $form
   * @param type $field
   * @param type $message
   * @return type
   */
  public static function setErrorField($form, $field, $message){
    $form['is_valid'] = false;
    $form['items'][$field]['is_valid'] = false;
    $form['items'][$field]['errors'][] = $message;
    return $form;
  }
  /**
   * Set error.
   * @param type $form
   * @param type $nl
   * @return string
   */
  public static function getErrors($form, $nl = '<br>'){
    $errors = null;
    if(isset($form['errors'])){
      foreach ($form['errors'] as $key => $value){
        $errors .= $value.$nl;
      }
    }
    foreach ($form['items'] as $key => $value) {
      if(!$value['is_valid']){
        foreach ($value['errors'] as $key2 => $value2){
          $errors .= '- '.$value2.$nl;
        }
      }
    }
    return $errors;
  }
  /**
   * Get all form errors as array.
   * @param PluginWfArray $form
   * @return Array
   */
  public static function getErrorsAsArray($form){
    $errors = array();
    if($form->get('errors')){
      foreach ($form->get('errors') as $key => $value){
        $errors['errors'][] = $value;
      }
    }
    foreach ($form->get('items') as $key => $value) {
      $item = new PluginWfArray($value);
      if(!$item->get('is_valid')){
        foreach ($item->get('errors') as $key2 => $value2){
          $errors['item'][$key][] =$value2;
        }
      }
    }
    return $errors;
  }
  /**
   * Validate email.
   * @param type $field
   * @param type $form
   * @param type $data
   * @return type
   */
  public function validate_email($field, $form, $data = array()){
    if(wfArray::get($form, "items/$field/is_valid") && wfArray::get($form, "items/$field/post_value")){
      if (!filter_var(wfArray::get($form, "items/$field/post_value"), FILTER_VALIDATE_EMAIL)) {
        $form = wfArray::set($form, "items/$field/is_valid", false);
        //$form = wfArray::set($form, "items/$field/errors/", __('?label is not an email!', array('?label' => wfArray::get($form, "items/$field/label"))));
        $form = wfArray::set($form, "items/$field/errors/", $this->i18n->translateFromTheme('?label is not an email!', array('?label' => $this->i18n->translateFromTheme(wfArray::get($form, "items/$field/label")))));
      }
    }
    return $form;
  }
  /**
   * Validate password.
   * @param type $field
   * @param type $form
   * @param type $data
   * @return type
   */
  public function validate_password($field, $form, $data = array()){
    if(wfArray::get($form, "items/$field/is_valid")){
      $validate = $this->validatePasswordAbcdef09(wfArray::get($form, "items/$field/post_value"));
      if (!wfArray::get($validate, 'success')) {
        $form = wfArray::set($form, "items/$field/is_valid", false);
        $form = wfArray::set($form, "items/$field/errors/", __('?label must have at lest one uppercase, lowercase, number and a minimum length of 8!', array('?label' => wfArray::get($form, "items/$field/label"))));
      }
    }
    return $form;
  }
  /**
   * Validate password.
   * @param type $password
   * @param type $settings
   * @return boolean
   */
  private function validatePasswordAbcdef09($password, $settings = array()) {
    // '$\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])(?=\S*[\W])\S*$';
    $data = array(
      'password' => $password,
      'settings' => $settings,
      'success' => false,
      'item' => array(
        'length' => array(
          'default' => '8',
          'match' => '(?=\S{[length],})',
          'result' => 2,
          'default_with_settings' => null 
        ),
        'lower_case' => array(
          'default' => true,
          'match' => '(?=\S*[a-z])',
          'result' => 2,
          'default_with_settings' => null 
        ),
        'upper_case' => array(
          'default' => true,
          'match' => '(?=\S*[A-Z])',
          'result' => 2,
          'default_with_settings' => null 
        ),
        'digit' => array(
          'default' => true,
          'match' => '(?=\S*[\d])',
          'result' => 2,
          'default_with_settings' => null 
        ),
        'special_character' => array(
          'default' => false,
          'match' => '(?=\S*[\W])',
          'result' => 2,
          'default_with_settings' => null 
        ),
      ),
      'match' => null
    );
    foreach ($data['item'] as $key => $value) {
      if(isset($data['settings'][$key])){
        $data['item'][$key]['default_with_settings'] = $data['settings'][$key];
      }else{
        $data['item'][$key]['default_with_settings'] = $data['item'][$key]['default'];
      }
    }
    if($data['item']['length']['default_with_settings']){
      // Replace length tag.
      $data['item']['length']['match'] = str_replace('[length]', $data['item']['length']['default_with_settings'], $data['item']['length']['match']);
    }
    $data['match'] = '$\S*';
    foreach ($data['item'] as $key => $value) {
      if($data['item'][$key]['default_with_settings']){
        $data['match'] .= $data['item'][$key]['match'];
        $data['item'][$key]['result'] = preg_match('$\S*'.$data['item'][$key]['match'].'\S*$', $data['password']);
      }
    }
    $data['match'] .= '\S*$';
    if (preg_match($data['match'], $data['password'])){
      $data['success'] = true;
    }
    return $data;
  }
  /**
   * Validate equal.
   * @param type $field
   * @param type $form
   * @param type $data
   * @return type
   */
  public function validate_equal($field, $form, $data = array('value' => 'some value')){
    if(wfArray::get($form, "items/$field/is_valid")){
      if (wfArray::get($form, "items/$field/post_value") != wfArray::get($data, 'value')) {
        $form = wfArray::set($form, "items/$field/is_valid", false);
        $form = wfArray::set($form, "items/$field/errors/", __('?label is not equal to expected value!', array('?label' => wfArray::get($form, "items/$field/label"))));
      }
    }
    return $form;
  }
  /**
   * Validate date.
   * @param type $field
   * @param type $form
   * @param type $data
   * @return type
   */
  public function validate_date($field, $form, $data = array()){
    if(wfArray::get($form, "items/$field/is_valid")){
      if (!PluginWfForm_v2::isDate(wfArray::get($form, "items/$field/post_value"))){
        $form = wfArray::set($form, "items/$field/is_valid", false);
        $form = wfArray::set($form, "items/$field/errors/", __('?label is not a date!', array('?label' => wfArray::get($form, "items/$field/label"))));
      }
    }
    return $form;
  }
  /**
   * Check if value is a date.
   * @param type $value
   * @return boolean
   */
  public static function isDate($value){
    if(strtotime($value)){
      $format_datetime = 'Y-m-d H:i:s';
      $format_date = wfDate::format();
      $d = DateTime::createFromFormat($format_datetime, $value);
      if($d && $d->format($format_datetime) == $value){
        return true;
      }else{
        $d = DateTime::createFromFormat($format_date, $value);
        if($d && $d->format($format_date) == $value){
          return true;
        }else{
          return false;
        }
      }
    }else{
      return false;
    }
  }
  /**
   * Validate numeric.
   * @param type $field
   * @param type $form
   * @param PluginWfArray $data
   * @return type
   */
  public function validate_numeric($field, $form, $data = array()){
    wfPlugin::includeonce('wf/array');
    $default = array('min' => 0, 'max' => 999999);
    $data = new PluginWfArray(array_merge($default, $data));
    if(wfArray::get($form, "items/$field/is_valid") && strlen(wfArray::get($form, "items/$field/post_value"))){
      if (!is_numeric(wfArray::get($form, "items/$field/post_value"))) {
        $form = wfArray::set($form, "items/$field/is_valid", false);
        $form = wfArray::set($form, "items/$field/errors/", __('?label is not numeric!', array('?label' => wfArray::get($form, "items/$field/label"))));
      }else{
        if(
                (int)wfArray::get($form, "items/$field/post_value") < (int)$data->get('min') || 
                (int)wfArray::get($form, "items/$field/post_value") > (int)$data->get('max')
                ){
        $form = wfArray::set($form, "items/$field/is_valid", false);
        $form = wfArray::set($form, "items/$field/errors/", __('?label must be between ?min and ?max!', array(
          '?label' => wfArray::get($form, "items/$field/label"),
          '?min' => $data->get('min'),
          '?max' => $data->get('max')
          )));
        }
      }
    }
    return $form;
  }
  /**
   * Save form to yml file.
   * @param PluginWfArray $form
   * @return boolean
   */
  public static function saveToYml($form){
    wfPlugin::includeonce('wf/array');
    wfPlugin::includeonce('wf/yml');
    $form = new PluginWfArray($form);
    if($form->get('yml/file') && $form->get('yml/path_to_key') && $form->get('items')){
      $yml = new PluginWfYml($form->get('yml/file'), $form->get('yml/path_to_key'));
      foreach ($form->get('items') as $key => $value) {
        $yml->set($key, wfArray::get($value, 'post_value'));
      }
      $yml->save();
      return true;
    }else{
      return false;
    }
    return false;
  }
  /**
   * Capture method.
   * @param type $plugin
   * @param type $method
   * @param type $form
   * @return type
   */
  public static function runCaptureMethod($plugin, $method, $form){
    wfPlugin::includeonce($plugin);
    $obj = wfSettings::getPluginObj($plugin);
    return $obj->$method($form);
  }
  /**
   * Method to test capture.
   * @return type
   */
  public function test_capture(){
    return array("alert('PluginWfForm_v2 method test_capture was tested! Replace to another to proceed your work.')");
  }
  /**
   * 
   */
  public function schema_capture($form){
    /**
     * Create save sql.
     */
    if($form->get('schema') && $form->get('table') && $form->get('mysql')){
      //$form = PluginWfForm_v2::setFormItemsDefaultFromDb($form);
      $field = new PluginWfArray(PluginWfForm_v2::getSchema($form));
      /**
       * Primary key (only one).
       */
      $primary = PluginWfForm_v2::getSchemaFieldPrimary($field->get());
      $primary_key = $primary->get('primary_key');
      $primary_type = $primary->get('primary_type');
      
      //wfHelp::yml_dump($field);
      $sql = "update ".$form->get('table')." set ";
//      foreach ($field as $key => $value) {
//        $sql .= "$key=?, ";
//      }
      foreach ($form->get('items') as $key => $value) {
        $sql .= "$key=?, ";
      }
      $sql = substr($sql, 0, strlen($sql)-2);
      $sql .= " where $primary_key=?;";
      $params = array();
      foreach ($form->get('items') as $key => $value) {
        $item = new PluginWfArray($value);
        
        $type = null;
        if(strstr($field->get("$key/type"), 'varchar(')){
          $type = 's';
        }elseif(strstr($field->get("$key/type"), 'int(')){
          $type = 'i';
        }
        
        $params[$key] = array('type' => $type, 'value' => $item->get('post_value'));
      }
      $params['primary_key'] = array('type' => $primary_type, 'value' => wfRequest::get($primary_key));
      $mysql_data = array('sql' => $sql, 'params' => $params);
      wfHelp::yml_dump($mysql_data);
      /**
       * Save to db.
       */
      wfPlugin::includeonce('wf/mysql');
      $mysql = new PluginWfMysql();
      $mysql->open($form->get('mysql'));
      $mysql->execute($mysql_data);
    }
   
  }
  /**
   * Include javascript file.
   */
  public static function widget_include(){
    $element = array();
    $element[] = wfDocument::createHtmlElement('script', null, array('src' => '/plugin/wf/form_v2/PluginWfForm_v2.js', 'type' => 'text/javascript'));
    wfDocument::renderElement($element);
  }
  /**
   * Email form data via capture call.
   * Call this as an capture method from form yml data to send multiple emails.
   #code-yml#
    capture:
      plugin: 'wf/form_v2'
      method: send
      data:
        phpmailer: 'Phpmailer data...'
        email:
          - 'me@world.com'
        script:
          - "location.reload();"
   #code#
   */
  public function send($form){
    /**
     * Mail settings.
     */
    $phpmailer = wfSettings::getSettingsFromYmlString($form->get('capture/data/phpmailer'));
    $phpmailer = new PluginWfArray($phpmailer);
    /**
     * Reply to.
     */
    if(wfRequest::get('email')){
      $phpmailer->set('ReplyTo', wfRequest::get('email'));
    }
    /**
     * Body.
     */
    $body = null;
    foreach ($form->get('items') as $key => $value) {
      $item = new PluginWfArray($value);
      $label = $item->get('label');
      $post_value = $item->get('post_value');
      $body .= "<p><strong>$label</strong></p>";
      $body .= "<p>$post_value</p>";
    }
    $body = "<html><body>".$body."</body></html>";
    $phpmailer->set('Body', $body);
    /**
     * Send.
     */
    wfPlugin::includeonce('wf/phpmailer');
    $wf_phpmailer = new PluginWfPhpmailer();
    foreach ($form->get('capture/data/email') as $key => $value) {
      $phpmailer->set('To', $value);
      $wf_phpmailer->send($phpmailer->get());
    }
    /**
     * Return script.
     */
    return $form->get('capture/data/script');
  }
}
