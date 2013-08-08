<?php
/*
Plugin Name: Garbage Disposal Plugin
Plugin URI: http://norfolkcounty.ca
Description: Allows users to find out how to dispose of items
Version: 1.0
Author: Adam Wills
Author URI: http://adamwills.com
License: GPLv2
*/

global $wpdb;
define("DATA_TABLE",$wpdb->prefix . "aw_garbage_plugin");
define("CAT_TABLE", $wpdb->prefix . "aw_garbage_cats");
define('WP_AW_GB_FOLDER', dirname(plugin_basename(__FILE__)));
define('WP_AW_GB_URL', plugins_url('',__FILE__));

// registers actions
register_activation_hook(__FILE__,'aw_gb_install');
register_uninstall_hook(__FILE__, 'aw_gb_uninstall' );


/******************************
        ADMIN FUNCTIONS
******************************/


// creates the database as needed.
function aw_gb_install() {
  global $wpdb;
  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  if(!$wpdb->get_var("show tables like '". DATA_TABLE."'")) {
    $sql = "CREATE TABLE " . DATA_TABLE ." (id mediumint(9) NOT NULL AUTO_INCREMENT,name tinytext NOT NULL,  garbage_type tinyint NOT NULL, PRIMARY KEY (id));";
    $wpdb->query($sql);
  }
  if(!$wpdb->get_var("show tables like '".CAT_TABLE."'")) {
    $sql = "CREATE TABLE ". CAT_TABLE . " ( id mediumint(9) NOT NULL, name tinytext NOT NULL, notes text, url tinytext, PRIMARY KEY (id) );";
    $wpdb->query($sql);
  }
}


// removes database as needed.
function aw_gb_uninstall() {
	global $wpdb;
	$wpdb->query("DROP TABLE IF EXISTS " . DATA_TABLE);
  $wpdb->query("DROP TABLE IF EXISTS " . CAT_TABLE);
}


// Add the admin options page
add_action('admin_menu', 'aw_gb_add_settings_page');

function aw_gb_add_settings_page() {
	$page_title = "Garbage Collection Importer";
	$menu_title = $page_title;
	$capability = "manage_options";
	$menu_slug = "garbage_collection_importer";
	$function = "aw_gb_settings_page";

	add_management_page( $page_title, $menu_title, $capability, $menu_slug, $function );
}


// Draw the options page
function aw_gb_settings_page() {
  ?>
	<div class="wrap">
    <div id="poststuff">
      <div id="post-body">
        <?php
          global $table_name;
            
          if (isset($_POST['cat_upload']))
          {
            $upload_dir = wp_upload_dir();
            $target_path = $upload_dir['basedir'] . "/" . basename( $_FILES['uploadedfile']['name']);
            $target_url = $upload_dir['baseurl'] . "/" . basename( $_FILES['uploadedfile']['name']);

            echo '<div id="message" class="updated fade"><p><strong>';
            if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $target_path))
            {
                $is_categories = true;
                update_option('aw_gb_cat_file_url', $target_url);
     
                $errorMsg = readAndDump($target_url,CAT_TABLE,$is_categories);
                if(empty($errorMsg))
                {
                    echo "The file ".  basename( $_FILES['uploadedfile']['name'])." has been successfully uploaded and imported into the database!";
                }
                else
                {
                    echo "Error occured while trying to import!<br />";
                    echo $errorMsg;
                }
            } 
            else
            {
                echo "There was an error uploading the file, please try again!";
            }
            echo '</strong></p></div>';
          }

          if(isset($_POST['file_upload']))
          {
            $upload_dir = wp_upload_dir();
            $target_path = $upload_dir['basedir'] . "/" . basename( $_FILES['uploadedfile']['name']);
            $target_url = $upload_dir['baseurl'] . "/" . basename( $_FILES['uploadedfile']['name']);

            echo '<div id="message" class="updated fade"><p><strong>';
            if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $target_path))
            {
                update_option('aw_gb_input_file_url', $target_url);
     
                $errorMsg = readAndDump($target_url,DATA_TABLE);
                if(empty($errorMsg))
                {
                    echo "The file ".  basename( $_FILES['uploadedfile']['name'])." has been successfully uploaded and imported into the database!";
                }
                else
                {
                    echo "Error occured while trying to import!<br />";
                    echo $errorMsg;
                }
            } 
            else
            {
                echo "There was an error uploading the file, please try again!";

            }
            echo '</strong></p></div>';
          }

          ?>
        <h1>Garbage Day Settings</h1>
          <div class="postbox">
            <h3><label for="title">Quick Usage Guide</label></h3>
            <div class="inside">
              <ol>
                <li>Specify the input file (Upload the CSV file or specify the location of a pre-uploaded CSV file)</li>
                <li>Hit the "Import to DB" button to import the CSV file content into the database table.</li>
              </ol>
            </div>
          </div>
          
          <div class="postbox">
            <h3><label for="title">1. Upload data file</label></h3>
            <div class="inside">
              <strong>Upload a File</strong>
              <br />
              <form enctype="multipart/form-data" action="<?php echo $_SERVER["REQUEST_URI"]; ?>" method="POST">
                <input type="hidden" name="file_upload" id="file_upload" value="true" />
                <input type="hidden" name="MAX_FILE_SIZE" value="1000000" />
                Choose a CSV file to upload: <input name="uploadedfile" type="file" /><br />
                <input type="submit" value="Upload File" />
              </form>
            </div>
          </div>
        
          <div class="postbox">
            <h3><label for="title">2. Upload Categories File</label></h3>
            <div class="inside">
              <form enctype="multipart/form-data" action="<?php echo $_SERVER["REQUEST_URI"]; ?>" method="POST">
                <input type="hidden" name="cat_upload" id="cat_upload" value="true" />
                <input type="hidden" name="MAX_FILE_SIZE" value="1000000" />
                Choose a CSV file to upload: <input name="uploadedfile" type="file" /><br />
                <input type="submit" value="Upload File" />
              </form>
            </div>
          </div>

          <div class="postbox">
            <h3><label for="title">3. Embed the shortcode!</label></h3>
            <div class="inside">
              <p>Once the tables have been filled, you just have to add the shortcode to display the garbage day tool!</p>
              <p>Simply add:</p>
              <pre>[know-to-throw]</pre>
              <p> in any page or post!!</p>
          </div>


        </div>
      </div>
    </div>
    <?php 
}

function readAndDump($src_file,$table_name,$is_categories=false)
{
  global $wpdb;
  $errorMsg = "";

  if(empty($src_file))
  {
    $errorMsg .= "<br />Input file is not specified";
    return $errorMsg;
  }

  $file_path = csv_to_db_get_abs_path_from_src_file($src_file); 
  
  $file_handle = fopen($file_path, "r");
  if ($file_handle === FALSE) 
  {
    // File could not be opened...
    $errorMsg .= 'Source file could not be opened!<br />';
    $errorMsg .= "Error on fopen('$file_path')";  // Catch any fopen() problems.
    return $errorMsg;
  }

  if($is_categories)
    $query = "DELETE FROM " . CAT_TABLE . " WHERE 1=1";
  else
    $query = "DELETE FROM " . DATA_TABLE . " WHERE 1=1";
  
  $results = $wpdb->query($query);
    
  $query_vals='';
  while (!feof($file_handle) ) 
  {
    $line_of_text = fgetcsv($file_handle, 1024);

    if($is_categories)
      $columns = 4;
    else 
      $columns = 2;
    
      if($line_of_text[0]!='') 
      {
        $query_vals.= "('".$wpdb->escape($line_of_text[0])."'";

        for($c=1;$c<$columns;$c++) 
        {
          $line_of_text[$c] = utf8_encode($line_of_text[$c]);
          $line_of_text[$c] = addslashes($line_of_text[$c]);
          $query_vals .= ",'".$wpdb->escape($line_of_text[$c])."'";
        }
        $query_vals.="),";
      }
  }
  
  $query_vals = substr_replace($query_vals, '', -1);
  if($is_categories)
    $query = "INSERT INTO " . CAT_TABLE . " (id, name, notes, url) VALUES $query_vals";
  else 
    $query = "INSERT INTO " . DATA_TABLE ." (name,garbage_type) VALUES $query_vals";

  
  $results = $wpdb->query($query);
  
  if(empty($results))
  {
      $errorMsg .= "<br />Insert into the Database failed for the following Query:<br />";
      $errorMsg .= $query;
  }
  fclose($file_handle);
  return $errorMsg;
}

function csv_to_db_get_abs_path_from_src_file($src_file)
{
  if(preg_match("/http/",$src_file))
  {
    $path = parse_url($src_file, PHP_URL_PATH);
    $abs_path = $_SERVER['DOCUMENT_ROOT'].$path;
    $abs_path = realpath($abs_path);
    if(empty($abs_path)){
      $wpurl = get_bloginfo('wpurl');
      $abs_path = str_replace($wpurl,ABSPATH,$src_file);
      $abs_path = realpath($abs_path);      
    }
  }
  else
  {
    $relative_path = $src_file;
    $abs_path = realpath($relative_path);
  }
  return $abs_path;
}

/******************************
        FRONT END FUNCTIONS
******************************/

function aw_gb_enqueue_scripts() {
  wp_enqueue_script('jquery-ui-autocomplete','jquery');
  wp_enqueue_script('ajax-search',plugins_url('/inc/ajax-search.js', __FILE__),array('jquery-ui-autocomplete'));
  wp_register_style('aw-gb-styles',plugins_url('css/styles.css',__FILE__)); 
  wp_enqueue_style('aw-gb-styles');
}
add_action('wp_enqueue_scripts','aw_gb_enqueue_scripts');


function aw_gb_show_garbage_form($atts) {

  $output = '<div class="ktw-search-input">';
  $output.= '<input type="text" name="searchKeyword" id="searchKeyword" placeholder="Search...">';
  $output.= '<input type="submit" value="Search!" id="submitSearch" class="btn">';
  $output.= '</div>';
  $output.= '<div id="results" class="ktw-results"></div>';
  return $output;
}
add_shortcode('know-to-throw','aw_gb_show_garbage_form');

function aw_gb_show_garbage_categories() {
  global $wpdb;
  $output = '<div class="ktw-categories">';
  $output.= '<ul class="ktw-results-list">';
  $results = aw_gb_get_categories();
  foreach($results as $result) {
      $output.="<li class='clearfix'><div class='ktw-image'><img src='". WP_AW_GB_URL ."/img/icon_garbage.jpg' alt='$result->method'></div><div class='ktw-summary'><strong>".$result->name."</strong></p><p>". stripcslashes($result->notes) ."</p></div><div class='ktw-link'><a href='$result->url'>More information on $result->name</a> - $result->url</div></li>";
  }
  $output.= '</ul>';
  $output.= '</div>';
  return $output;
}
add_shortcode('know-to-throw-categories','aw_gb_show_garbage_categories');

function aw_gb_get_categories() {
  global $wpdb;
  $query = "SELECT * FROM ".CAT_TABLE;
  $results = $wpdb->get_results($query);
  return $results;  
}

function aw_gb_do_search() {
  global $wpdb;
    $search = like_escape($_POST['search_string']);
    $query = "SELECT t1.name AS item, t2.name AS method, t2.notes AS notes, t2.url AS url FROM ".DATA_TABLE." AS t1 LEFT JOIN ".CAT_TABLE." AS t2 ON t2.id = t1.garbage_type WHERE t1.name LIKE '%$search%' LIMIT 50";
    $results = $wpdb->get_results($query);
    $output = '';
    if(count($results)>0) {
      if(count($results)==50) {
        $output.='<div class="ktw-many-results"><p>Yikes! It looks like there\'s a lot of matches for "'.$search.'." Try to be a bit more specific!</p><p>Here\'s the first 50.</p></div>';
      }
      else {
        $output.='<div class="ktw-results-count">We found ' . count($results) . ' matches for "' . $search . '".</div>';
      }
      $output.= '<ul id="KnowToThrow" class="ktw-results-list">';
      foreach($results as $result) {
        $output.="<li class='clearfix'><div class='ktw-image'><img src='". WP_AW_GB_URL ."/img/icon_garbage.jpg' alt='$result->method'></div><div class='ktw-summary'><strong>". stripslashes($result->item) ."</strong></p><p>". stripcslashes($result->notes) ."</p></div><div class='ktw-link'><a href='$result->url'>More information on $result->method</a></div></li>";
        
      }
      $output.= '</ul>';
    }
    else {
      $output.= "No results found.";
    }
    $output.= '<div class="ktw-suggest"><p>Couldn\'t find what you\'re looking for? <a href="#">Let us know!</a></p></div>';
    die($output);
}

add_action('wp_ajax_aw_gb_do_search','aw_gb_do_search');
add_action('wp_ajax_nopriv_aw_gb_do_search','aw_gb_do_search');


function aw_gb_suggest() {
  global $wpdb;
  $search = like_escape($_POST['search_string']);
  
  $query = "SELECT name FROM ".DATA_TABLE." WHERE name LIKE '%$search%' LIMIT 10";
  $output = json_encode($wpdb->get_results($query));
  die($output);
}

add_action('wp_ajax_aw_gb_suggest','aw_gb_suggest');
add_action('wp_ajax_nopriv_aw_gb_suggest','aw_gb_suggest');