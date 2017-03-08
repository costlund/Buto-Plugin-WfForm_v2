function plugin_wf_form_v2(){
  /**
   * Move controls if form has own layout.
   * @param {type} data
   * @returns {unresolved}
   */
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
  /**
   * Show modal map for a hidden input.
   * @type plugin_wf_form_v2
   */
  this.showMap = function(id){
    /**
     * Default data.
     */
    var map_data = {lat: '61.9', lng: '18.6', map_type_id: 'hybrid', zoom: '4'};
    /**
     * Replace default data with input data.
     */
    if(document.getElementById(id).value.length > 0){
      var input_data = JSON.parse(document.getElementById(id).value);
      for (var key in input_data) {
        map_data[key] = input_data[key];
      }
    }
    /**
     * Create modal.
     */
    PluginWfBootstrapjs.modal({id: 'modal_map', url: null, lable: 'Map', size: '', fade: false});
    document.getElementById('modal_map_body').style.height = '300px';
    /**
     * Create map.
     */
    var map = PluginGoogleMaps.load({
      'onchange': function(type, map){
        var c = map.getCenter();
        //document.getElementById('cords').innerHTML = c.lat()+' '+c.lng()+' '+map.getMapTypeId()+' '+map.getZoom();
        marker.setPosition(PluginGoogleMaps.getMap().getCenter());
      }, 
      'id':'modal_map_body',
      'style':'height:300px;width:100%;',
      'class':'',
      'data':{
        'center':{'geocodezzz':'Halmstad', 'lat': map_data.lat, 'lng': map_data.lng}, 
        'zoom': map_data.zoom, 
        'scrollwheel':true,
        'draggable':true, 
        'mapTypeId': map_data.map_type_id, 
        'markerzzz':[{'position':{'geocode':'Göteborg', 'latzzz': '20', 'lngzzz': '20'},'draggable':true}]
      }
    });
    /**
     * Search box.
     * https://developers.google.com/maps/documentation/javascript/examples/places-searchbox
     */
    var input = document.createElement('input');
    input.id = 'pac-input';
    input.className = 'controls';
    input.type = 'text';
    input.placeholder = 'Search';
    input.style.margin = '8px';
    input.style.padding = '4px';
    //input.style.display = 'none';
    document.getElementById('modal_map_footer').appendChild(input);
    var searchBox = new google.maps.places.SearchBox(input);
    PluginGoogleMaps.getMap().controls[google.maps.ControlPosition.TOP_RIGHT].push(input);
//    // Bias the SearchBox results towards current map's viewport.
//    PluginGoogleMaps.getMap().addListener('bounds_changed', function() {
//      searchBox.setBounds(PluginGoogleMaps.getMap().getBounds());
//    });
    searchBox.addListener('places_changed', function() {
      //console.log('places_changed...');
    });
    var markers = [];
    // Listen for the event fired when the user selects a prediction and retrieve
    // more details for that place.
    searchBox.addListener('places_changed', function() {
      var places = searchBox.getPlaces();
      if (places.length == 0) {
        return;
      }
      // Clear out the old markers.
      markers.forEach(function(marker) {
        marker.setMap(null);
      });
      markers = [];
      // For each place, get the icon, name and location.
      var bounds = new google.maps.LatLngBounds();
      places.forEach(function(place) {
        if (!place.geometry) {
          console.log("Returned place contains no geometry");
          return;
        }
        var icon = {
          url: place.icon,
          size: new google.maps.Size(71, 71),
          origin: new google.maps.Point(0, 0),
          anchor: new google.maps.Point(17, 34),
          scaledSize: new google.maps.Size(25, 25)
        };
        // Create a marker for each place.
//        markers.push(new google.maps.Marker({
//          map: PluginGoogleMaps.getMap(),
//          icon: icon,
//          title: place.name,
//          position: place.geometry.location
//        }));
        if (place.geometry.viewport) {
          // Only geocodes have viewport.
          bounds.union(place.geometry.viewport);
        } else {
          bounds.extend(place.geometry.location);
        }
      });
      PluginGoogleMaps.getMap().fitBounds(bounds);
    });    
    
    /**
     * Create span.
     */
    var span = document.createElement('span');
    span.id = 'cords';
    span.style.float = 'left';
    document.getElementById('modal_map_footer').appendChild(span);
    /**
     * BUTTON current position button.
     */
    var btn = document.createElement('a');
    btn.innerHTML = 'Set my location';
    btn.href = '#!';
    btn.className = 'btn btn-defaultzzz';
    btn.setAttribute('data-dismisszzz', 'modal');
    btn.onclick = function(){
      navigator.geolocation.getCurrentPosition(function(location){
        PluginGoogleMaps.getMap().setCenter(new google.maps.LatLng(location.coords.latitude, location.coords.longitude));
        PluginGoogleMaps.getMap().setZoom(15);
      }, function(){alert('Could not get current position!');});
    }
    document.getElementById('modal_map_footer').appendChild(btn);
    /**
     * Create clear button.
     */
    var btn = document.createElement('button');
    btn.innerHTML = 'Clear';
    btn.className = 'btn btn-default';
    btn.setAttribute('data-dismiss', 'modal');
    btn.onclick = function(){
      var c = PluginGoogleMaps.getMap().getCenter();
      document.getElementById(id).value = '';
      document.getElementById(id).onchange();
    }
    document.getElementById('modal_map_footer').appendChild(btn);
    /**
     * Create OK button.
     */
    var btn = document.createElement('button');
    btn.innerHTML = 'Ok';
    btn.className = 'btn btn-default';
    btn.setAttribute('data-dismiss', 'modal');
    btn.onclick = function(){
      var c = PluginGoogleMaps.getMap().getCenter();
      document.getElementById(id).value = '{"lat": "'+c.lat()+'", "lng": "'+c.lng()+'", "map_type_id": "'+PluginGoogleMaps.getMap().getMapTypeId()+'", "zoom": "'+PluginGoogleMaps.getMap().getZoom()+'"}';
      document.getElementById(id).onchange();
    }
    document.getElementById('modal_map_footer').appendChild(btn);
    /**
     * Set marker.
     */
    var marker = new google.maps.Marker({
          position: PluginGoogleMaps.getMap().getCenter(),
          map: PluginGoogleMaps.getMap(),
          title: 'Hello World!'
        });
    
        
        
  }
}
var PluginWfForm_v2 = new plugin_wf_form_v2();












