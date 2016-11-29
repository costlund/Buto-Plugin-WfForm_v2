function plugin_wf_form_v2(){
  this.renderLayout = function(data){
    var layout = document.getElementById(data.id+'_layout');
    var elements = layout.getElementsByTagName('*');
    for(var i=0;i<elements.length;i++){
      var element = elements[i];
      var innerHTML = element.innerHTML;
      if(innerHTML.substr(0, 5)=='item['){
        element.innerHTML = '';
        var json = JSON.parse(innerHTML.substr(4));
        for(var i=0; i<json.length; i++){
          if(json[i].type == 'control'){
            if(document.getElementById(data.id+'_'+json[i].id)){element.appendChild(document.getElementById(data.id+'_'+json[i].id));}
          }else if(json[i].type == 'label'){
            if(document.getElementById('label_'+data.id+'_'+json[i].id)){element.appendChild(document.getElementById('label_'+data.id+'_'+json[i].id));}
          }else if(json[i].type == 'info'){
            if(document.getElementById('info_'+data.id+'_'+json[i].id)){element.appendChild(document.getElementById('info_'+data.id+'_'+json[i].id));}
          }else if(json[i].type == 'div'){
            if(document.getElementById('div_'+data.id+'_'+json[i].id)){element.appendChild(document.getElementById('div_'+data.id+'_'+json[i].id));}
          }
        }
      }
    }
    return null;
  }
}
var PluginWfForm_v2 = new plugin_wf_form_v2();







