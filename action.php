<?php
/**
 * DokuWiki Action Plugin FontFace
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Anika Henke <anika@selfthinker.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');
if(!defined('DOKU_LF')) define('DOKU_LF', "\n");

require_once(DOKU_PLUGIN.'action.php');

/**
 * All DokuWiki plugins to interfere with the event system
 * need to inherit from this class
 */
class action_plugin_fontface extends DokuWiki_Action_Plugin {

    // register hook
    function register(&$controller) {
        $controller->register_hook('TPL_METAHEADER_OUTPUT','BEFORE', $this, '_addFontCode');
        if ( ($this->getConf('technique')=='cufon') || ($this->getConf('technique')=='typeface')){
            $controller->register_hook('TPL_CONTENT_DISPLAY','AFTER', $this, '_lateFontInit'); // or rather TPL_ACT_RENDER?
        }
    }

    /**
     * Add font code (JS and CSS) depending on chosen technique
     *
     * @param unknown_type $event
     * @param unknown_type $param
     */
    function _addFontCode(&$event, $param) {

        $pluginSysDir = DOKU_INC.'lib/plugins/fontface/';
        $pluginDir    = DOKU_BASE.'lib/plugins/fontface/';
        $libDir       = $pluginDir.'lib/';
        $fontSysDir   = $pluginSysDir.'fonts/';
        $fontDir      = $pluginDir.'fonts/';

        $technique    = $this->getConf('technique');
        $fontFileName = $this->getConf('fontFile');
        $fontName     = $this->getConf('fontName');
        $headings     = $this->getConf('headings');

        $JSfiles  = array();
        $JSembed  = '';
        $CSSfiles = array();
        $CSSembed = '';

        // don't apply anything if no technique is chosen
        if (empty($technique)) {
            return false;
        }

        // prepare CSS and JS to embed depending on the technique
        switch ($technique) {
            case 'fontface':
                $fontEOT  = $fontFileName.'.eot';
                $fontWOFF = $fontFileName.'.woff';
                $fontTTF  = $fontFileName.'.ttf';
                $fontSVG  = $fontFileName.'.svg';

                // check if files exist
                if (!$this->_isFileOk($fontSysDir.$fontEOT,  $fontDir.$fontEOT,  'fontFile') ||
                    !$this->_isFileOk($fontSysDir.$fontWOFF, $fontDir.$fontWOFF, 'fontFile') ||
                    !$this->_isFileOk($fontSysDir.$fontTTF,  $fontDir.$fontTTF,  'fontFile') ||
                    !$this->_isFileOk($fontSysDir.$fontSVG,  $fontDir.$fontSVG,  'fontFile')) {
                    return false;
                }

                $CSSembed = "@font-face {".NL.
                            "  font-family: '".$fontName."';".NL.
                            "  src: url('".$fontDir.$fontEOT."');".NL.
                            "  src: url('".$fontDir.$fontEOT."?#iefix') format('embedded-opentype'),".NL.
                            "       url('".$fontDir.$fontWOFF."') format('woff'),".NL.
                            "       url('".$fontDir.$fontTTF."')  format('truetype'),".NL.
                            "       url('".$fontDir.$fontSVG."#".str_replace(' ', '', $fontName)."') format('svg');".NL.
                            "  font-weight: normal;".NL.
                            "  font-style: normal;".NL.
                            "}";
                break;

            case 'google':
                // check if required option is set
                if (empty($fontFileName)) {
                    msg("The '<strong>fontFileName</strong>' config setting is <strong>not set</strong>.", -1);
                    return false;
                }

                $CSSfiles = array(
                                'http://fonts.googleapis.com/css?family='.str_replace(' ', '+', $fontFileName)
                            );
                break;

            case 'kernest':
                // check if required option is set
                if (empty($fontFileName)) {
                    msg("The '<strong>fontFileName</strong>' config setting is <strong>not set</strong>.", -1);
                    return false;
                }

                $CSSfiles = array(
                                'http://kernest.com/fonts/'.$fontFileName.'.css'
                            );
                break;

            case 'cufon':
                $fontFile = $fontFileName.'.font.js';

                // check if required options are set and file exists
                if (!$this->_isFileOk($fontSysDir.$fontFile, $fontDir.$fontFile, 'fontFile')) {
                    return false;
                }
                if (empty($headings)){
                    msg("No headings chosen to be replaced.", -1);
                    return false;
                }

                $JSfiles     = array(
                                   $libDir.'cufon/cufon-yui.js',
                                   $fontDir.$fontFile
                               );
                $headingsCode = '';
                foreach(explode(',',$headings) as $heading){
                    $headingsCode .= "('".$heading."')";
                }
                $JSembed     = "Cufon.replace".$headingsCode.";";
                break;

            case 'typeface':
                $fontFile = $fontFileName.'.typeface.js';

                // check if file exists
                if (!$this->_isFileOk($fontSysDir.$fontFile, $fontDir.$fontFile, 'fontFile')) {
                    return false;
                }

                $JSfiles  = array(
                                $libDir.'typeface/typeface-0.15.js',
                                $fontDir.$fontFile
                            );
                break;

            case 'sifr':
                $fontFile = $fontFileName.'.swf';

                // check if required options are set and file exists
                if (!$this->_isFileOk($fontSysDir.$fontFile, $fontDir.$fontFile, 'fontFile')) {
                    return false;
                }
                if (empty($headings)){
                    msg("No headings chosen to be replaced.", -1);
                    return false;
                }

                $JSfiles  = array(
                                $libDir.'sifr/sifr.js'
                            );
                $JSembed  = "var ".$fontFileName." = { src: '".$fontDir.$fontFile."' };".NL.
                            "sIFR.activate(".$fontFileName.");".NL.
                            "sIFR.replace(".$fontFileName.", { selector: '".$headings."' });";
                $CSSfiles = array(
                                $libDir.'sifr/sifr.css'
                            );
                $headingsCode = array();
                foreach(explode(',',$headings) as $key => $heading){
                    $headingsCode[$key] .= ".sIFR-active ".$heading;
                }
                $headingsCode = explode(',',$headingsCode);
                $CSSembed = $headingsCode." { visibility: hidden; }";
                break;
        }

        // add styles (done automatically through JS for cufon and sifr)
        // if not set (for techniques other than cufon and sifr), set them through CSS as usual
        if ( $this->getConf('addStyles') &&
             !empty($headings) &&
             ($technique!='cufon') &&
             ($technique!='sifr') ) {
            $CSSembed .= $headings." { font-family: '".$fontName."', ".$this->getConf('genericFamily')."; }";
        }

        // include all relevant CSS files
        if (!empty($CSSfiles)){
            foreach($CSSfiles as $CSSfile) {
                $event->data['link'][] = array(
                    'type'    => 'text/css',
                    'rel'     => 'stylesheet',
                    'media'   => 'screen',
                    'href'    => $CSSfile
                );
            }
        }
        // embed all relevant CSS code
        if (!empty($CSSembed)){
            $event->data['style'][] = array(
                'type'    => 'text/css',
                'media'   => 'screen',
                '_data'   => $CSSembed
            );
        }
        // include all relevant JS files
        if (!empty($JSfiles)){
            foreach($JSfiles as $JSfile) {
                $event->data['script'][] = array(
                    'type'    => 'text/javascript',
                    'charset' => 'utf-8',
                    '_data'   => '',
                    'src'     => $JSfile
                );
            }
        }
        // embed all relevant JS code
        if (!empty($JSembed)){
            $event->data['script'][] = array(
                'type'    => 'text/javascript',
                'charset' => 'utf-8',
                '_data'   => $JSembed
            );
        }

    }

    /**
     * Loads initialisation of cufon and typeface to avoid Flash Of Unstyled Content in IE6-8
     *
     * @param unknown_type $event
     * @param unknown_type $param
     */
    function _lateFontInit(&$event, $param) {
        switch ($this->getConf('technique')) {
            case 'cufon':
                $JSembed = 'Cufon.now();';
                break;
            /* causes problems in IE6 and IE7, page doesn't display at all ("Operation aborted")
               because TPL_CONTENT_DISPLAY is too early (should be added just before </body>)
            case 'typeface':
                $JSembed = '_typeface_js.initialize();';
                break;
            */
            default:
                return false;
        }
        echo NL.'<script type="text/javascript">'.$JSembed.'</script>'.NL;
    }

    /**
     * Check if file option is set and if it exists
     *
     * @param string $file          File to check (path to system directory)
     * @param string $fileDisplay   File to display in error message (path to web directory)
     * @param string $fileConfig    Name of config option
     */
    function _isFileOk($file, $fileDisplay, $fileConfig) {
        if (empty($file)) {
            msg("The '<strong>".$fileConfig."</strong>' config setting is <strong>not set</strong>.", -1);
            return false;
        } else if (!file_exists($file)) {
            msg("The file <strong>".$fileDisplay."</strong> (".$fileConfig.") <strong>does not exist</strong>.", -1);
            return false;
        }
        return true;
    }


}

// vim:ts=4:sw=4:
