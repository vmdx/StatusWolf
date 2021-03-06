<?php
/**
 * dash.php
 *
 * View for custom dashboards
 *
 * Author: Mark Troyer <disco@box.com>
 * Date Created: 10 July 2013
 *
 * @package StatusWolf.Views
 */

$_session_data = $_SESSION[SWConfig::read_values('auth.sessionName')];
$_sw_conf = SWConfig::read_values('statuswolf');
$db_conf = $_sw_conf['session_handler'];

if (array_key_exists('dashboard_id', $_session_data['data']))
{
  $sw_db = new mysqli($db_conf['db_host'], $db_conf['db_user'], $db_conf['db_password'], $db_conf['database']);
  if (mysqli_connect_error())
  {
    throw new SWException('Unable to connect to shared search database: ' . mysqli_connect_errno() . ' ' . mysqli_connect_error());
  }
  $this->loggy->logDebug($this->log_tag . "Loading saved dashboard id " . $_session_data['data']['dashboard_id']);
  $dashboard_query = sprintf("SELECT * FROM saved_dashboards WHERE id='%s'", $_session_data['data']['dashboard_id']);
  if ($dashboard_result = $sw_db->query($dashboard_query))
  {
    if ($dashboard_result->num_rows && $dashboard_result->num_rows > 0)
    {
      $dashboard_data = $dashboard_result->fetch_assoc();
      if ($dashboard_data['shared'] == 0 && $dashboard_data['user_id'] != $_session_data['user_id'])
      {
        $this->loggy->logDebug($this->log_tag . 'Access violation, user id ' . $_session_data['user_id'] . ' trying to view private dashboard owned by user id ' . $dashboard_data['user_id']);
        $dashboard_config = 'Not Allowed';
      }
      else
      {
        $this->loggy->logDebug($this->log_tag . 'Dashboard config found, loading');
        $dashboard_config = $dashboard_data;
        $incoming_widgets = unserialize($dashboard_data['widgets']);
        $dashboard_config['widgets'] = $incoming_widgets;
      }
    }
    else
    {
      $this->loggy->logDebug($this->log_tag . 'Dashboard id ' . $_session_data['data']['dashboard_id'] . ' was not found');
      $dashboard_config = 'Not Found';
    }
  }
}
else
{
  $dashboard_config = null;
}

?>

<link rel="stylesheet" href="<?php echo URL; ?>app/css/widget_base.css">
<link rel="stylesheet" href="<?php echo URL; ?>app/css/dash.css">
<link rel="stylesheet" href="<?php echo URL; ?>app/css/popups.css?v=1.0">
<link rel="stylesheet" href="<?php echo URL; ?>app/css/push-button.css">

<div class="container">
  <div id="dash-container"></div>
</div>

<div id="save-dashboard-popup" class="popup mfp-hide">
  <div id="save-dashboard-form">
    <form onsubmit="return false;">
      <h5 style="display: inline-block;">Title: </h5>
      <div class="popup-form-data" style="display: inline-block">
        <input type="text" class="input" id="dashboard-title" name="dashboard-title" value="" style="width: 250px;">
      </div>
      <div class="push-button" style="display: inline-block; margin-left: 10px;">
        <input type="checkbox" id="shared" name="shared"><label for="shared"><span class="iconic iconic-x-alt red"></span><span class="binary-label"> Shared Dashboard</span></label>
      </div>
    </form>
  </div>
  <div class="flexy widget-footer" style="margin-top: 10px;">
    <div class="widget-footer-button" id="cancel-save-dashboard-button" onClick="$.magnificPopup.close()"><span class="iconic iconic-x-alt"><span class="font-reset"> Cancel</span></span></div>
    <div class="glue1"></div>
    <div class="widget-footer-button" id="save-dashboard-button" onClick="save_click_handler(event, 0)"><span class="iconic iconic-download"><span class="font-reset"> Save</span></span></div>
  </div>
</div>

<div id="success-popup" class="popup mfp-hide"><h5>Success</h5><div class="popup-form-data">Your dashboard has been saved.</div></div>
<div id="failure-popup" class="popup mfp-hide"><h5>Error</h5><div id="failure-info" class="popup-form-data">There was an error when saving your dashboard, please try again later.</div></div>
<div id="confirmation-popup" class="popup mfp-hide">
  <div id="confirmation-main">
    <div id="confirmation-info" class="popup-form-data"></div>
  </div>
  <div class="flexy widget-footer" style="margin-top: 10px;">
    <div class="widget-footer-button" id="cancel-confirm-button" onClick="$.magnificPopup.close()"><span class="iconic iconic-x-alt"><span class="font-reset"> Cancel</span></span></div>
    <div class="glue1"></div>
    <div class="widget-footer-button" id="confirm-save-button"><span class="iconic iconic-download"><span class="font-reset"> Overwrite</span></span></div>
  </div>
</div>

<link rel="stylesheet" href="<?php echo URL; ?>app/css/datetimepicker.css">
<link rel="stylesheet" href="<?php echo URL; ?>app/css/toggle-buttons.css">
<link rel="stylesheet" href="<?php echo URL; ?>app/css/table.css">
<link rel="stylesheet" href="<?php echo URL; ?>app/css/loader.css">
<link rel="stylesheet" href="<?php echo URL; ?>app/css/tooltip.css">

<script type="text/javascript" src="<?php echo URL; ?>app/js/sw_lib.js"></script>
<script type="text/javascript" src="<?php echo URL; ?>app/js/lib/jquery-ui.js"></script>
<script type="text/javascript" src="<?php echo URL; ?>app/js/lib/bootstrap.js"></script>
<script type="text/javascript" src="<?php echo URL; ?>app/js/lib/date.js"></script>
<script type="text/javascript" src="<?php echo URL; ?>app/js/lib/md5.js"></script>
<script type="text/javascript" src="<?php echo URL; ?>app/js/lib/dygraph-combined.js"></script>
<script type="text/javascript" src="<?php echo URL; ?>app/js/status_wolf_colors.js"></script>
<script type="text/javascript" src="<?php echo URL; ?>app/js/lib/jquery.autocomplete.js"></script>


<?php
// Register available widgets

$widget_main = WIDGETS;
$widget_dir_iterator = new DirectoryIterator($widget_main);
$widgets = array();
$widget_list = array();
foreach($widget_dir_iterator as $fileinfo)
{
  if ($fileinfo->isDot()) { continue; }
  if ($fileinfo->isDir())
  {
    $widgets[] = $fileinfo->getFilename();
  }
}
foreach($widgets as $widget_key)
{
  $widget_info = file_get_contents($widget_main . DS . $widget_key . DS . $widget_key . '.json');
  $widget_info = implode('', explode("\n", $widget_info));
  $widget_list[$widget_key] = json_decode($widget_info, true);
  echo '<script type="text/javascript" src="' . URL . WIDGETS_URL . $widget_key . '/js/' . $widget_list[$widget_key]['name'] . '.js"></script>';
}

?>

<script type="text/javascript">

  $('title').text('Dashboard - StatusWolf');

  var _session_data = '<?php echo json_encode($_session_data); ?>';
  if (typeof(_session_data) == "string")
  {
    document._session_data = eval('(' + _session_data + ')');
  }
  else
  {
    document._session_data = _session_data
  }
  var _sw_conf = '<?php echo json_encode($_sw_conf); ?>';
  if (typeof(_sw_conf) == "string")
  {
    document._sw_conf = eval('(' + _sw_conf + ')');
  }
  else
  {
    document._sw_conf = _sw_conf;
  }
  var dashboard_config = <?php if ($dashboard_config) { echo json_encode($dashboard_config); } else { echo 'null'; } ?>;

  $(document).ready(function() {
    var widgets = eval('(<?php echo json_encode($widget_list); ?>)');
    var loaded_widgets = [];
    this.sw_url = '<?php echo URL; ?>';

    $('#menu-placeholder').replaceWith('<div class="dashboard-menu left-button dropdown menu-btn" id="dashboard-menu">');
    $('#dashboard-menu').append('<span class="flexy" id="dashboard-menu-content" data-toggle="dropdown">')
    $('#dashboard-menu-content').append('<span class="menu-label" id="dashboard-menu-label">Dashboard</span>');
    $('#dashboard-menu').append('<ul class="dropdown-menu sub-menu-item" id="dashboard-menu-options" role="menu" aria-labelledby="dLabel">');
    $('#dashboard-menu-options').append('<li id="clear-dashboard-menu-choice"><a href="<?php echo URL; ?>dashboard/"><span>Clear</span></a></li>');
    $('#dashboard-menu-options').append('<li class="flexy dropdown" id="add-widget-menu-item"><span>Add Widget</span></span><span class="iconic iconic-play"></span></li>');
    $('#add-widget-menu-item').append('<ul class="dropdown-menu sub-menu" id="add-widget-menu-options">');
    $.each(widgets, function(widget_index, widget_data) {
      var widget_type = widget_data.name.split('.');
      $('#add-widget-menu-options').append('<li onClick="add_widget(\'' + widget_type[1] + '\')"><span>' + widget_data.title + '</span></li>');
      $('head').append('<link rel="stylesheet" href="<?php echo URL . WIDGETS_URL; ?>' + widget_index + '/css/' + widget_data.name + '.css">');

    });
    $('#dashboard-menu-options').append('<li class="flexy dropdown" id="load-dashboard-menu-item"><span>Load Dashboard</span></span><span class="iconic iconic-play"></span></li>');
    $('#load-dashboard-menu-item').append('<ul class="dropdown-menu sub-menu" id="load-dashboard-menu-options">');
    $('#dashboard-menu-options').append('<li id="save-dashboard-menu-choice"><span>Save Dashboard</span></li>');

    build_dashboard_list_menu();

    if (dashboard_config !== null && typeof dashboard_config !== "undefined")
    {
      if (typeof dashboard_config === "string" && dashboard_config.length > 1)
      {
        if (dashboard_config.match(/Not Allowed/))
        {
          $('.container').append('<div id="not-allowed-popup" class="popup"><h5>Not Allowed</h5><div class="popup-form-data">You do not have permission to view this dashboard</div></div>');
          $.magnificPopup.open({
            items: {
              src: '#not-allowed-popup'
              ,type: 'inline'
            }
            ,preloader: false
            ,removalDelay: 300
            ,mainClass: 'popup-animate'
            ,callbacks: {
              open: function() {
                $('.navbar').addClass('blur');
                $('.container').addClass('blur');
              }
              ,close: function() {
                $('.container').removeClass('blur');
                $('.navbar').removeClass('blur');
                window.history.pushState("", "StatusWolf", "/dashboard/");
              }
            }
          });
        }
        else if(dashboard_config.match(/Not Found/))
        {
          $('.container').append('<div id="not-found-popup" class="popup"><h5>Not Found</h5><div class="popup-form-data">The dashboard was not found.</div></div>');
          $.magnificPopup.open({
            items: {
              src: '#not-found-popup'
              ,type: 'inline'
            }
            ,preloader: false
            ,removalDelay: 300
            ,mainClass: 'popup-animate'
            ,callbacks: {
              open: function() {
                $('.navbar').addClass('blur');
                $('.container').addClass('blur');
              }
              ,close: function() {
                $('.container').removeClass('blur');
                $('.navbar').removeClass('blur');
                window.history.pushState("", "StatusWolf", "/dashboard/");
              }
            }
          });
        }
      }
      else
      {
        if (typeof(dashboard_config) === "string")
        {
          dashboard_config = eval('(' + dashboard_config + ')');
        }
        $('title').text(dashboard_config.title + ' - StatusWolf');
        $('input#dashboard-title').val(dashboard_config.title);
        $.each(dashboard_config.widgets, function(widget_id, query_data) {
          if (query_data.widget_type === "graphwidget")
          {
            $('#dash-container').append('<div class="widget-container" id="' + widget_id + '" data-widget-type="' + query_data.widget_type + '">');
            new_widget = $('div#' + widget_id).graphwidget({sw_url: '<?php echo URL; ?>'});
            widget_object = $(new_widget).data('sw-' + new_widget.attr('data-widget-type'));
            widget_object.populate_search_form(query_data, widget_object);
            $('#' + widget_id).removeClass('transparent');
          }
          else
          {
            console.log('unknown widget type: ' + query_data.widget_type);
          }
        });
      }
    }

    $('#save-dashboard-menu-choice').magnificPopup({
      items: {
        src: '#save-dashboard-popup'
        ,type: 'inline'
      }
      ,preloader: false
      ,focus: '#dashboard-title'
      ,removalDelay: 300
      ,mainClass: 'popup-animate'
      ,callbacks: {
        open: function() {
          setTimeout(function() {
            $('.container').addClass('blur');
            $('.navbar').addClass('blur');
          }, 150);
        }
        ,close: function() {
          $('.container').removeClass('blur');
          $('.navbar').removeClass('blur');
        }
      }
    });

  });

  function add_widget(widget_type)
  {
    var username = "<?php echo $_session_data['username'] ?>";
    var widget_id = "widget" + md5(username + new Date.now().getTime());
    var widget;
    $('#dash-container').append('<div class="widget-container" id="' + widget_id + '" data-widget-type="' + widget_type + '">');
    if (widget_type === "graphwidget")
    {
      widget = $('#' + widget_id).graphwidget({sw_url: '<?php echo URL; ?>'});
      setTimeout(function() {
        widget.data('sw-graphwidget').sw_graphwidget_editparamsbutton.click();
      }, 250);
    }
    setTimeout(function() {
      $('#' + widget_id).removeClass('transparent');
    }, 100);
  }

  function clone_widget(widget)
  {
    var username = "<?php echo $_session_data['username'] ?>";
    var widget_id = "widget" + md5(username + new Date.now().getTime());
    var widget_element = $(widget.element);
    var widget_type = $(widget_element).attr('data-widget-type');
    if (widget_type === "graphwidget")
    {
      $('#dash-container').append('<div class="widget-container" id="' + widget_id + '" data-widget-type="' + widget_type + '">');
      new_widget = $('div#' + widget_id).graphwidget({sw_url: '<?php echo URL; ?>'});
      new_widget_object = $(new_widget).data('sw-' + new_widget.attr('data-widget-type'));
      new_widget_object.populate_search_form(widget.query_data, new_widget_object, 'clone');
      $('#' + widget_id).removeClass('transparent');
    }
    else
    {
      console.log('unknown widget type: ' + widget_element.widget_type);
    }

  }

  function build_dashboard_list_menu()
  {
    var api_url = '<?php echo URL; ?>api/get_saved_dashboards';
    api_query = {user_id: document._session_data.user_id};
    $.ajax({
      url: api_url
      ,type: 'POST'
      ,data: api_query
      ,dataType: 'json'
      ,success: function(data) {
        my_dashboards = data['user_dashboards'];
        shared_dashboards = data['shared_dashboards'];
        $('#load-dashboard-menu-options').empty();
        $('#load-dashboard-menu-options').append('<li class="menu-section"><span>My Dashboards</span></li>');
        if (my_dashboards)
        {
          $.each(my_dashboards, function(i, dashboard) {
            $('#load-dashboard-menu-options').append('<li><span><a href="<?php echo URL; ?>dashboard/' + dashboard['id'] + '">' + dashboard['title'] + '</span></li>');
          });
        }
        if (shared_dashboards)
        {
//          $('#load-dashboard-menu-options').append('<li class="menu-section"><span class="divider"></span></li>');
          $('#load-dashboard-menu-options').append('<li class="divider">');
          $('#load-dashboard-menu-options').append('<li class="menu-section"><span>Shared Dashboards</span></li>');
          $.each(shared_dashboards, function(i, shared) {
            $('#load-dashboard-menu-options').append('<li><span><a href="<?php echo URL; ?>dashboard/' + shared['id'] + '">' + shared['title'] + ' (' + shared['username'] + ')</a></span></li>');
          });
        }
      }
    });
  }


function save_click_handler(event, confirmation, dashboard_id)
  {
    var dashboard_widgets = {};
    var dashboard_config = {};
    var widget_list = $('.widget-container');
    if (widget_list.length > 0)
    {
      $.each(widget_list, function(i, widget) {
        var sw_widget = $(widget).data('sw-' + $(widget).attr('data-widget-type'));
        var widget_id = $(widget).attr('id');
        if (typeof sw_widget.query_data === "undefined")
        {
          console.log('no query data defined for this widget');
        }
        else
        {
          dashboard_widgets[widget_id] = sw_widget.query_data;
          dashboard_widgets[widget_id]['widget_type'] = $(widget).attr('data-widget-type');
        }
      })
    }
    else
    {
      alert('Blank dashboard, saving you from yourself and refusing to save this...');
    }
    if (typeof(dashboard_id) == "undefined")
    {
      dashboard_id = md5("dashboard" + document._session_data.username + new Date.now().getTime());
    }
    dashboard_config = { id: dashboard_id
      ,title: $('input#dashboard-title').val()
      ,shared: $('#shared').prop('checked')?1:0
      ,username: document._session_data.username
      ,user_id: document._session_data.user_id
      ,widgets: dashboard_widgets };

    save_dash_url = "<?php echo URL; ?>api/save_dashboard/" + dashboard_id;
    if (confirmation > 0)
    {
      save_dash_url += "/Confirm";
    }
    $.ajax({
      url: save_dash_url
      ,type: 'POST'
      ,dataType: 'json'
      ,data: dashboard_config
      ,success: function(data) {
        if (typeof(data) == "string")
        {
          data = eval('(' + data + ')');
        }
        if (data.query_result === "Error")
        {
          switch (data.query_info)
          {
            case "Title":
              $('#confirmation-info').empty().append("<span>A dashboard with that name already exists, overwrite?</span>");
              $('#confirm-save-button').click( function(event) {
                save_click_handler(event, 1, data.dashboard_id);
              });
              $.magnificPopup.open({
                items: {
                  src: '#confirmation-popup'
                  ,type: 'inline'
                }
                ,preloader: false
                ,mainClass: 'popup-animate'
                ,callbacks: {
                  close: function() {
                    $('#confirmation-popup').remove();
                  }
                }
              });
              return;
            default:
              $.magnificPopup.open({
                items: {
                  src: '#failure-popup'
                  ,type: 'inline'
                }
                ,preloader: false
                ,removalDelay: 300
                ,mainClass: 'popup-animate'
                ,callbacks: {
                  close: function() {
                    $('#failure-popup').remove();
                  }
                }
              })
              setTimeout(function() {
                $.magnificPopup.close();
              }, 750);
          }
        }
        $.magnificPopup.open({
          items: {
            src: '#success-popup'
            ,type: 'inline'
          }
          ,preloader: false
          ,removalDelay: 300
          ,mainClass: 'popup-animate'
          ,callbacks: {
            open: function() {
              $('.navbar').addClass('blur');
              $('.container').addClass('blur');
            }
            ,close: function() {
              $('.container').removeClass('blur');
              $('.navbar').removeClass('blur');
              $('#success-popup').remove();
            }
          }
        });
        setTimeout(function() {
          $.magnificPopup.close();
        }, 750);
      }
    });
  }

  $(window).resize(function() {
    var widget_container = $('div.maximize-widget'),
        widget_main;
    if (widget_container.length > 0)
    {
      $(this).scrollTop(0);
      widget_main = $(widget_container).children('div.widget').children('div.widget-front').children('div.widget-main');
    }
    else
    {
      widget_container = $('div.shrink-widget');
      widget_main = $(widget_container).children('div.widget').children('div.widget-front').children('div.widget-main');
      $(widget_container).removeClass('shrink-widget');
    }
    var graph_div = $(widget_main).children('div.graph-widget-graphdiv');
    var graph_legend = $(widget_main).children('div.graph-widget-legend-container');
    $(widget_main).css('height', ($(widget_container).innerHeight() - 10));
    $(graph_div).css('height', ($(widget_main).innerHeight() - $(graph_legend).outerHeight(true)));
    $(graph_legend).css('width', $(widget_main).innerWidth());
  })

</script>