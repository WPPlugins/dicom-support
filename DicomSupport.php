<?php
/*
Plugin Name: DICOM Support
Plugin URI:
Description: DICOM support for Wordpress: allows to upload DICOM (*.dcm) files in the media library and add them to a post. The display is done using the DICOM Web Viewer (<a href="https://github.com/ivmartel/dwv">DWV</a>).
Version: 0.5.2
Author: ivmartel
Author URI: https://github.com/ivmartel
*/

if (!class_exists("DicomSupport")) {

// DicomSupport class.
class DicomSupport {

  /**
  * Constructor.
  */
  function __construct() {
    load_plugin_textdomain('dcmobj');

    // add DICOM mime type to allowed upload
    add_filter('upload_mimes', array($this, 'upload_mimes'));

    // add 'dcm' DICOM view shortcode
    add_shortcode('dcm', array($this, 'dcm_shortcode'));
    // enqueue scripts on front end
    add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));

    // use 'dcm' shortcode when adding media to blog post
    add_filter('media_send_to_editor', array($this, 'media_send_to_editor'), 10, 3);
    // modify the output of the gallery short-code
    add_filter('post_gallery', array($this, 'post_gallery'), 10, 3);

    add_action('admin_print_footer_scripts', array($this, 'admin_print_footer_scripts'));
    add_action('wp_ajax_query-attachments', array($this, 'wp_ajax_query-attachments'));
  }

  /**
  * Add DICOM (*.dcm) as a supported MIME type.
  * @see https://developer.wordpress.org/reference/hooks/upload_mimes/
  * @see http://codex.wordpress.org/Plugin_API/Filter_Reference/upload_mimes
  * @param mime_types List of existing MIME types.
  */
  function upload_mimes($mime_types) {
    // add dcm to the list of mime types
    $mime_types['dcm'] = 'application/dicom';
    // return list
    return $mime_types;
  }

  /**
  * Create the DWV html.
  * @param urls The string of the urls to load.
  * @param width The width of the display.
  * @param height The height of the display.
  */
  function create_dwv_html($urls, $width = 0, $height = 0) {
    // enqueue base scripts
    wp_enqueue_script('dwv-simple');
    wp_enqueue_script('wpinit');

    // html var names
    $id = uniqid();
    $containerDivId = "dwv-" . $id;
    $style = "";
    if ( !empty($width) && $width != 0 &&
        !empty($height) && $height != 0 ) {
        $style = ' style="width:'.$width.'px;height:'.$height.'px"';
    }

    // create app script
    // dwv.wp.init and listener flags were added in wp_enqueue_scripts defined below
    $script = '
    // start app function
    function startApp'.$id.'() {
        // main application
        var myapp = new dwv.App();
        // initialise the application
        myapp.init({
            "containerDivId": "'.$containerDivId.'",
            "gui": ["tool"],
            "tools": ["Scroll", "ZoomAndPan", "WindowLevel"],
            "isMobile": true
        });
        myapp.loadURLs(['.$urls.']);
        dwv.gui.appendResetHtml(myapp);
    }
    // launch when page and i18n are loaded
    document.addEventListener("DOMContentLoaded", function (/*event*/)
    {
        domContentLoaded = true;
        launchApp'.$id.'();
    });
    dwv.i18nOnLoaded( function () {
        i18nLoaded = true;
        launchApp'.$id.'();
    });
    function launchApp'.$id.'() {
        if ( domContentLoaded && i18nLoaded ) {
            startApp'.$id.'();
        }
    }';
    // add script to queue
    wp_add_inline_script('wpinit', $script);
    
    // create html
    $html = '
    <!-- Main container div -->
    <div id="'.$containerDivId.'">
      <!-- Toolbar -->
      <div class="toolbar"></div>
      <!-- Layer Container -->
      <div class="layerContainer"'.$style.'>
      <canvas class="imageLayer">Only for HTML5 compatible browsers...</canvas>
      </div><!-- /layerContainer -->
    </div><!-- /dwv -->
    ';

    // 'full screen' link
    $list = explode(',', $urls);
    if (count($list) == 1) {
        $query = trim($urls,'"');
        $extra = "";
    } else {
        $element = trim($list[0],'"');
        $pos = strripos($element,'/');
        $query = substr($element, 0, $pos+1);
        $query .= "?file=" . substr($element, $pos+1);
        for ($i = 1; $i < count($list); $i++) {
            $element = trim($list[$i],'"');
            $pos = strripos($element,'/');
            $query .= "&file=" . substr($element, $pos+1);
        }
        $extra = "&dwvReplaceMode=void";
    }
    $link = plugins_url('viewers/simplistic/index.html', __FILE__);
    $link .= "?input=" . urlencode($query) . $extra;
    $href = "<small><a href=\"" . $link . "\" target=\"_blank\">Full screen</a></small>";
    $html .= $href;
    
    return $html;
  }

  /**
  * Interpret the 'dcm' shortcode to insert DICOM data in posts.
  * @see http://codex.wordpress.org/Shortcode_API
  * @param atts An associative array of attributes.
  * @param content The enclosed content.
  */
  function dcm_shortcode($atts, $content = null) {
    // check that we have a src attribute
    if ( empty($atts['src']) ) {
      return;
    }
    // width/height
    $width = 0;
    if ( !empty($atts['width']) ) {
        $width = $atts['width'];
    }
    $height = 0;
    if ( !empty($atts['height']) ) {
        $height = $atts['height'];
    }
    // split file list: given as "file1, file2",
    //   it needs to be passed as "file1", "file2"
    $fileList = array_map('trim', explode(',', $atts['src']));
    $urls = '"' . implode('","', $fileList) . '"';
    // return html
    return $this->create_dwv_html($urls, $width, $height);
  }

  /**
  * Enqueue scripts for the front end.
  * @see https://codex.wordpress.org/Plugin_API/Action_Reference/wp_enqueue_scripts
  */
  function wp_enqueue_scripts() {
    // i18n
    wp_register_script( 'i18next', plugins_url('ext/i18next/i18next.min.js', __FILE__ ),
      null, null );
    wp_register_script( 'i18next-xhr', plugins_url('ext/i18next/i18nextXHRBackend.min.js', __FILE__ ),
      array('i18next'), null );
    wp_register_script( 'i18next-langdetect', plugins_url('ext/i18next/i18nextBrowserLanguageDetector.min.js', __FILE__ ),
      array('i18next'), null );
    // data decoders
    wp_register_script( 'pdfjs-ad', plugins_url('ext/pdfjs/arithmetic_decoder.js', __FILE__ ),
      null, null );
    wp_register_script( 'pdfjs-util', plugins_url('ext/pdfjs/util.js', __FILE__ ),
      null, null );
    wp_register_script( 'pdfjs-jpg', plugins_url('ext/pdfjs/jpg.js', __FILE__ ),
      array('pdfjs-ad', 'pdfjs-util'), null );
    wp_register_script( 'pdfjs-jpx', plugins_url('ext/pdfjs/jpx.js', __FILE__ ),
      array('pdfjs-ad', 'pdfjs-util'), null );
    wp_register_script( 'rii-loss', plugins_url('ext/rii-mango/lossless-min.js', __FILE__ ),
      null, null );
    // DWV base
    wp_register_script( 'dwv', plugins_url('dwv-0.18.0.min.js', __FILE__ ),
      array('i18next-xhr', 'i18next-langdetect', 'pdfjs-jpg', 'pdfjs-jpx', 'rii-loss'), null );
    // DWV simplistic viewer
    wp_register_script( 'dwv-simple', plugins_url('viewers/simplistic/appgui.js', __FILE__ ),
      array( 'dwv' ), null );
    // wp special
    wp_register_script( 'wpinit', plugins_url('wpinit.js', __FILE__ ),
      array( 'dwv' ), null );
    wp_localize_script( 'wpinit', 'wp', array('pluginsUrl' => plugins_url()) );
    $script = '
    // call special wp init
    dwv.wp.init();
    // listener flags
    var domContentLoaded = false;
    var i18nLoaded = false;';
    wp_add_inline_script('wpinit', $script);
  }

  /**
  * Insert shortcode when adding media to a blog post.
  * @see https://developer.wordpress.org/reference/hooks/media_send_to_editor/
  * @param html The default generated html.
  * @param id The id of the post.
  * @param attachment The post attachment.
  */
  function media_send_to_editor($html, $id, $attachment) {
    $post = get_post( $id ); // returns a WP_Post object
    // only process DICOM objects
    if ( $post->post_mime_type == 'application/dicom' ) {
      if ( !empty( $attachment['url'] )) {
        $html = '[dcm src="'.$attachment['url'].'"] ';
      }
    }
    return $html;
  }

  /**
  * Override media manager javascript functions to
  *  allow to select DICOM files to create galleries.
  * @see http://shibashake.com/wordpress-theme/how-to-expand-the-wordpress-media-manager-interface
  */
  function admin_print_footer_scripts() { ?>
    <script type="text/javascript">
    if (wp && wp.media) {
      // Add custom post type filters
      l10n = wp.media.view.l10n = typeof _wpMediaViewsL10n === 'undefined' ? {} : _wpMediaViewsL10n;
      wp.media.view.AttachmentFilters.Uploaded.prototype.createFilters = function () {
        var type = this.model.get('type');
        var types = wp.media.view.settings.mimeTypes;
        var text;
        if ( types && type ) {
          text = types[ type ];
        }

        var filters = {
          all: {
            text: text || l10n.allMediaItems,
            props: {
              uploadedTo: null,
              orderby: 'date',
              order: 'DESC'
            },
            priority: 20
          },

          uploaded: {
            text: l10n.uploadedToThisPost,
            props: {
              uploadedTo: wp.media.view.settings.post.id,
              orderby: 'menuOrder',
              order: 'ASC'
            },
            priority: 30
          },

          dicom: {
            text: 'DICOM',
            props: {
              type: 'application/dicom',
              uploadedTo: wp.media.view.settings.post.id,
              orderby: 'date',
              order: 'DESC'
            },
            priority: 10
          }
        };
        // Add post types only for gallery
        if (this.options.controller._state.indexOf('gallery') !== -1) {
          delete(filters.all);
          filters.image = {
            text: 'Images',
            props: {
              type: 'image',
              uploadedTo: null,
              orderby: 'date',
              order: 'DESC'
            },
            priority: 10
          };
          _.each( wp.media.view.settings.postTypes || {}, function ( text, key ) {
            filters[ key ] = {
              text: text,
              props: {
                type: key,
                uploadedTo: null,
                orderby: 'date',
                order: 'DESC'
              }
            };
          });
        }
        this.filters = filters;
      } // End create filters

      // Adding our search results to the gallery
      wp.media.view.MediaFrame.Post.prototype.mainGalleryToolbar = function ( view ) {
        var controller = this;

        this.selectionStatusToolbar( view );

        view.set( 'gallery', {
          style: 'primary',
          text: l10n.createNewGallery,
          priority: 60,
          requires: { selection: true },

          click: function () {
            var selection = controller.state().get('selection'),
            edit = controller.state('gallery-edit');
            //models = selection.where({ type: 'image' });

            // Don't filter based on type
            edit.set( 'library', selection);
            /*edit.set( 'library', new wp.media.model.Selection( selection, {
              props:    selection.props.toJSON(),
              multiple: true
            }) );*/

            this.controller.setState('gallery-edit');
          }
        });
      };
    } // end if (wp)
    </script>
  <?php }

  /**
  * Modify the output of the gallery short-code for DICOM files.
  * @see https://developer.wordpress.org/reference/hooks/post_gallery/
  * @see https://codex.wordpress.org/Plugin_API/Filter_Reference/post_gallery
  * @param output The current output.
  * @param atts The attributes from the gallery shortcode.
  * @param instance Unique numeric ID of this gallery shortcode instance.
  */
  function post_gallery($output, $atts, $instance) {
    // attributes
    $atts = shortcode_atts( array(
      'order' => 'ASC',
      'orderby' => 'menu_order ID',
      'include' => '',
      'size' => 'full'
      ), $atts, 'gallery'
    );

    // size
    $width = 0;
    $height = 0;
    if ( $atts['size'] == "thumbnail" ) {
        $width = 100;
        $height = 100;
    }
    else if ( $atts['size'] == "medium" ) {
        $width = 250;
        $height = 250;
    }
    else if ( $atts['size'] == "large" ) {
        $width = 500;
        $height = 500;
    }

    // get attachements
    // $atts['ids'] have been copied to $atts['include'],
    // see wp_include/media.php: function gallery_shortcode
    $_attachments = get_posts( array(
      'include' => $atts['include'],
      'post_status' => 'inherit',
      'post_type' =>  'attachment',
      'post_mime_type' => 'application/dicom',
      'order' => $atts['order'],
      'orderby' => $atts['orderby'] )
    );
    // build url list as string
    $urls = '';
    foreach ( $_attachments as $att ) {
      if ( $urls != '' ) {
        $urls .= ',';
      }
      $urls .= '"' . $att->guid . '"';
    }
    // return html
    // an empty output leads to default behaviour which will
    // be the case for non DICOM attachements
    $html = '';
    if ( $urls != '' ) {
      $html = $this->create_dwv_html($urls, $width, $height);
    }
    return $html;
  }

} // end DicomSupport class

// Instanciate to create hooks.
$dcmSuppInstance = new DicomSupport();

} // end if (!class_exists("DicomSupport")) {

?>
