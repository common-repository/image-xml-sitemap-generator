<?php
/*
Plugin Name: Image XML-Sitemap Generator
Plugin URI: http://www.butenhoff.net/plugins/fkbff-sitemap-generator/
Description: Plugin zum erzeugen von Image Sitemaps im XML-Format f&uuml;r dein WordPress Blog.
Author: Frank Butenhoff
Version: 0.6
Author URI: http://www.butenhoff.net/
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

define( 'FXS_PLUGIN_DIRECTORY', 'fkbff-sitemap-generator');
define( 'EMU2_I18N_DOMAIN', 'fxs' );

add_action ('admin_menu', 'fxs_init');

function fxs_init () {
  fxs_set_lang_file();
  fxs_addStylesheet();
  if (function_exists ('add_submenu_page')) {
    add_submenu_page ('tools.php', __('Erzeuge Image Sitemap', EMU2_I18N_DOMAIN), __('Image Sitemap erzeugen', EMU2_I18N_DOMAIN), 'manage_options', 9, 'fxs_sitemapGenerate');
  }
}

function fxs_addStylesheet() {
  wp_register_style('fkbffXmlSitemapStyle', '/wp-content/plugins/'. FXS_PLUGIN_DIRECTORY .'/style.css');
	wp_enqueue_style('fkbffXmlSitemapStyle');
}

function fxs_set_lang_file() {
	$currentLocale = get_locale();
	if (!empty($currentLocale)) {
		$moFile = dirname(__FILE__) . "/lang/" . $currentLocale . ".mo";
		if (@file_exists($moFile) && is_readable($moFile)) {
			load_textdomain(EMU2_I18N_DOMAIN, $moFile);
		}
	}
}

function fxs_sitemapGenerate () {
  if ($_POST['submit']) {
    $sitemap = new fxs_xmlSitemap();
    $st = $sitemap->generate();
    $sitemapurl = $sitemap->sitemapUrl;
    if (!$st) {
      print '<br />
      <div class="error">
        <h2>'.__('Fehler!', EMU2_I18N_DOMAIN).'</h2>
        <p>'.__('Die Image XML-Sitemap ist korrekt erzeugt worden, konnte aber nicht als', EMU2_I18N_DOMAIN).' <strong>' . $sitemapurl . '</strong> '.__('gespeichert werden.', EMU2_I18N_DOMAIN).'</p>
        <p>'.__('Bitte &uuml;berpr&uuml;fe die <a href="http://codex.wordpress.org/Changing_File_Permissions" target="_blank" class="lnk">Schreibrechte</a>.', EMU2_I18N_DOMAIN).'</p>
        <p>'.__('Alternativ dazu, kannst du per FTP oder Konsole versuchen die Rechte auf 0666 zu setzten und es dann erneut versuchen.', EMU2_I18N_DOMAIN).'</p>
      </div>';  
      exit();
    }
    print '
    <div class="wrap">
      <h2>'.__('Image XML Sitemap', EMU2_I18N_DOMAIN).'</h2>
      <p>'.__('Die Image Sitemap wurde im XML-Format erzeugt und gespeichert. Zur Kontrolle kannst du in einem Web-Browser die', EMU2_I18N_DOMAIN).' <a href="'.$sitemapurl.'" target="_blank" class="lnk">Sitemap</a> '.__('&ouml;ffnen und sie auf Fehler pr&uuml;fen.', EMU2_I18N_DOMAIN).'</p>
      <p>'.__('Die erzeugte Sitemap kannst du mit den <a href="http://www.google.com/webmasters/tools/" target="_blank" class="lnk">Webmaster Tools</a> von Google einbinden, oder direkt einen', EMU2_I18N_DOMAIN).' <a href="http://www.google.com/webmasters/sitemaps/ping?sitemap='.$sitemapurl.'" target="_blank" class="lnk">'.__('Ping', EMU2_I18N_DOMAIN).'</a> '.__('f&uuml;r Google setzen.', EMU2_I18N_DOMAIN).'</p>
    </div>
    ';
  } else { 
    print '
    <div class="wrap">
      <h2>'.__('Image XML Sitemap', EMU2_I18N_DOMAIN).'</h2>
      <p>'.__('Image Sitemaps sind ein guter Weg Google und anderen Suchmaschienen zu erz&auml;hlen welche Bilder du ins Netz gestellt hast, erzeuge eine Sitemap und andere Menschen finden deine Inhalte schneller und besser.', EMU2_I18N_DOMAIN).'</p>
      <strong>'.__('Sitemap erzeugen', EMU2_I18N_DOMAIN).'</strong>
      <form id="options_form" method="post" action="">
        <div class="submit">
          <input type="submit" name="submit" id="sb_submit" value="'.__('Erzeuge Image Sitemap', EMU2_I18N_DOMAIN).'" />
        </div>
      </form>
      <p>'.__('Du kannst oberhalb den Button klicken um eine Sitemap zu generieren. Diese Image Sitemap kannst du dann zum Beispiel mit den Google Webmastertools oder der robots.txt nutzen.', EMU2_I18N_DOMAIN).'</p>
    </div>
    ';
  }
}

class fxs_xmlSitemap {
  public $WPDB;
  public $sitemapFile;
  public $sitemapUrl;
  public $xml = '';
  public $TAB = "\t";
  public $BR = "\n";
  
  function __construct() {
    global $wpdb;
    $this->WPDB = $wpdb;
    $this->sitemapFileLocation();
  }
  
  function sitemapFileLocation( $r=false ) {
    $this->sitemapFile = ABSPATH . '/sitemap-image.xml';
    $SERVERNAME = $_SERVER["SERVER_NAME"]!=''?'http://'.$_SERVER["SERVER_NAME"]:'';
    $this->sitemapUrl  = $SERVERNAME . '/sitemap-image.xml';
  }
  
  function getXmlHeader() {
    $this->xml  .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $this->xml  .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
  }
  
  function getXmlFooter() {
    $this->xml  .= '</urlset>'."\n";
  }
  
  function generate () {
    $posts = $this->WPDB->get_results ("
      SELECT 
        id, post_content 
      FROM 
        ". $this->WPDB->posts ."
      WHERE 
        post_status = 'publish' 
        AND 
        (post_type = 'post' OR post_type = 'page') 
      ORDER BY 
        post_date ASC 
    ");
    if (empty ($posts)) {
      return false;
    } else {
      $this->getXmlHeader();
      foreach ($posts as $post) { 
        $permalink = get_permalink($post->id); 
        if (preg_match_all ("/<img[^>]+>/ui", $post->post_content, $matches, PREG_SET_ORDER)) {
          $this->xml .= "<url>".$this->BR;
          $this->xml .= $this->TAB."<loc>$permalink</loc>".$this->BR;
          $this->addImageContent( $matches );
          $this->xml .= "</url>".$this->BR;
        }
      }
      $this->getXmlFooter();
    }
    return $this->writeToFile();
  }
  
  function addImageContent( $m ) {
    $temp = '';
    foreach ($m as $match) {
      preg_match('/title=("[^"]*")/i',$match[0], $title);
      preg_match('/alt=("[^"]*")/i',$match[0], $alt);
      preg_match('/src=("[^"]*")/i',$match[0], $src);
      $title = $title[1]!=''?str_replace('"','',$title[1]):'';
      $alt = $alt[1]!=''?str_replace('"','',$alt[1]):'';
      $src = $src[1]!=''?str_replace('"','',$src[1]):'';
      if ( $temp != $src ) {
        $this->xml .= $this->TAB."<image:image>".$this->BR;
        $this->xml .= $this->TAB.$this->TAB."<image:loc>{$src}</image:loc>".$this->BR;
        $this->xml .= $alt!=''?$this->TAB.$this->TAB."<image:caption>" . htmlspecialchars ( $alt, ENT_COMPAT ) . "</image:caption>".$this->BR:'';
        $this->xml .= $title!=''?$this->TAB.$this->TAB."<image:title>" . htmlspecialchars ( $title, ENT_COMPAT ) . "</image:title>".$this->BR:'';
        $this->xml .= $this->TAB."</image:image>".$this->BR;
        $temp = $src;
      }
    }
  }
  
  function writeToFile() {
    if ($this->isFileWritable($_SERVER["DOCUMENT_ROOT"]) || $this->isFileWritable($this->sitemapFile)) {
      if (file_put_contents ($this->sitemapFile, $this->xml)) {
        chmod( $this->sitemapFile, 0666 );
        return true;
      }
    }
    return false;
  }
  
  function isFileWritable() {
    if (!is_writable($this->sitemapFile)) {
      if (!@chmod($this->sitemapFile, 0666)) {
        $pathtofilename = dirname($this->sitemapFile);
        if (!is_writable($pathtofilename)) {
          if (!@chmod($pathtoffilename, 0666)) {
            return false;
          }
        }
      }
    }
    return true;
  }
}
?>