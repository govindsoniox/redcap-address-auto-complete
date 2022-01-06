<?php

// Set the namespace defined in your config file
namespace STPH\addressAutoComplete;

use \REDCap;

if( file_exists("vendor/autoload.php") ){
    require 'vendor/autoload.php';
}

// Declare your module class, which must extend AbstractExternalModule 
class addressAutoComplete extends \ExternalModules\AbstractExternalModule {

    /** @var string */    
    private $api_source;

    /** @var object */ 
    private $api_config;
  
    /** @var string */    
    private $api_key;
    
    /** @var string */    
    private $target_field;

    /** @var array */ 
    private $target_advanced;    

    /** @var string */    
    private $target_meta;

    /** @var array */ 
    private $lang;

    private $outputFormat;

    /** @var bool */    
    private $isSourceValid;

    /** @var bool */     
    private $isEnabledForDataEntry;
    
    /** @var bool */
    private $isEnabledForSurvey;

    /** @var bool */
    private $isEnabledAdvancedSave;
    
    /** @var bool */
    private $isEnabledCustomAddress;

   /**
    * Constructs the class
    *
    */
    public function __construct()
    {        
        parent::__construct();
       // Other code to run when object is instantiated
       $this->api_source = "";
       $this->api_logo = "";
       $this->api_config = (Object)[];
       $this->target_field = "";
       $this->target_advanced = [];
       $this->target_meta = "";
       $this->lang = [];
       $this->outputFormat = "";

       $this->isSourceValid = false;
       $this->isEnabledForDataEntry = false;
       $this->isEnabledForSurvey = false;

       $this->isEnabledAdvancedSave = false;
       $this->isEnabledCustomAddress = false;
    }

   /**
    * Hooks Address Auto Complete module to redcap_every_page_top    
    *
    * @param string $project_id    
    * @since 1.0.0
    *
    */
    public function redcap_every_page_top($project_id = null) : void {
                       
        $this->setSettings();
       
        if($this->isPage("DataEntry/index.php") && $this->isEnabledForDataEntry) {
            $this->renderModule();
        }

    }


   /**
    * Hooks Address Auto Complete module to redcap_survey_page_top    
    *
    * @since 1.0.0
    *
    */    
    function redcap_survey_page_top() : void  {
        
        if($this->isEnabledForSurvey) {
            $this->renderModule();
        }

    }


   /**
    * Hooks into redcap_module_configuration_settings
    *
    * @param string $project_id
    * @param array $settings
    *
    * @since 1.0.0
    *
    */        

    function redcap_module_configuration_settings($project_id, $settings) : array {
        
        if($project_id != null) {
            foreach ($settings as &$setting) {
                if( $setting["key"] == "api-description") {
                    $setting["name"] = '<div data-pid="'.$project_id.'" data-url="'.$this->getUrl("requestHandler.php").'" style="padding:15px;display:inline-block;" id="api-description-wrapper">'.$this->generate_config_description($project_id).'</div><script src='.$this->getUrl("js/config.js").'></script>';
                }
            }

        }
        return $settings;

    }

   /**
    * Generates config description
    *
    * @param string $pid
    * @param string $source
    *
    * @since 1.0.0
    *
    */      
    private function generate_config_description ($pid, $source=null): string {
        
        if($source == null) {
            $saved_source = $this->getProjectSetting("api-source", $pid);

            if(empty($saved_source)) {
                return "";
            }
            $this->api_source = $this->getProjectSetting("api-source", $pid);
        } else {
            $this->api_source = $source;
        }

        $config = $this->getSourceConfig();
        $base64 = $this->getApiLogoAsBase64();
        $html_logo = "";
        $html_identifier = "<b>".$this->api_source."</b>";
        $html_description = "<p>".$config->description."</p>";
        $html_documentation = "";

        if($base64) {
            $html_logo = "<image src='data:image/svg+xml;base64, ".$base64."' width='30' alt='api-logo' style='margin-right:20px;'>";            
        }

        if(!empty($config->documentation)) {
            $html_documentation = "<a target='_blank' href='".$config->documentation."'><i class='fas fa-book'></i> API Documentation</a>";
        }

        if(!empty($config->registration)) {
            $html_registration = "<a style='margin-left:10px' target='_blank' href='".$config->registration."'><i class='fas fa-key'></i> API Registration</a>";
        }

        return $html_logo . $html_identifier . $html_description . $html_documentation . $html_registration;

    }


   /**
    * Returns config description via AJAX
    *
    * @param string $pid
    * @param string $source
    *
    * @since 1.0.0
    *
    */
    public function getConfigDescription($pid, $source): void {
        header('Content-Type: application/json; charset=UTF-8');

        $html = $this->generate_config_description($pid, $source);
        $response = ["html"=>$html];

        echo json_encode($response);
    }

   /**
    * Sets settings
    *
    *
    * @since 1.0.0
    *
    */     
    private function setSettings() : void {

        //  Check if target field is of type text (also checks if field has been set)
        if( REDCap::getFieldType($this->getProjectSetting("target-field")) != "text") {
            return;
        }

        $this->api_source = $this->getProjectSetting("api-source");

        if($this->api_source != "your.api.here") {
            $this->isSourceValid = true;
        }

        $this->api_limit = $this->getProjectSetting("api-limit");

        $this->api_config = $this->getSourceConfig();

        $this->api_key = $this->getProjectSetting("api-key");
        
        $this->target_field = $this->getProjectSetting("target-field");
        $this->target_meta = $this->getProjectSetting("target-meta");

        $this->isEnabledForDataEntry = $this->getProjectSetting("enable-for-data-entry");
        $this->isEnabledForSurvey = $this->getProjectSetting("enable-for-survey");
        $this->isEnabledAdvancedSave = $this->getProjectSetting("enable-advanced-save");
        $this->isEnabledCustomAddress = $this->getProjectSetting("enable-custom-address");

        if($this->isEnabledAdvancedSave) {

            $this->target_advanced = array(
                "street" => $this->getProjectSetting("field-street"),
                "number" => $this->getProjectSetting("field-number"),
                "code" => $this->getProjectSetting("field-code"),
                "city" => $this->getProjectSetting("field-city"),
                "country" => $this->getProjectSetting("field-country"),
                "note" => $this->getProjectSetting("field-note")
            );

        }


        $this->outputFormat = $this->getProjectSetting("output-format");

        $this->debug = $this->getProjectSetting("javascript-debug") == true;
    }

   /**
    * Parses Sources from json
    *
    *
    * @since 1.0.0
    *
    */         
    private function getSourceConfig() : object {

        $json = file_get_contents( __DIR__ ."/sources/sources.json");
        $parsed = json_decode($json);

        $filtered = array_filter($parsed->sources, function($el){
            return $el->identifier == $this->api_source;
        });

        return reset($filtered);

    }

   /**
    * Gets Base Url from config
    *
    *
    * @since 1.0.0
    *
    */         
    private function getBaseUrl(): string {

        //$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
        //$protocol = $isSecure ? 'https://' : 'http://';
        $protocol = "https://";

        return $protocol . $this->api_config->url;
    }

   /**
    * Gets Endpoint Url 
    *
    *
    * @since 1.2.0
    *
    */   
    private function getEndpointUrl(): string {
        $base = $this->getBaseUrl();

        if($this->api_config->term == "") {
            $api_term_string = "/";
        } else {
            $api_term_string = '&' . $this->api_config->term . '=';
        }

        return $base . $this->api_config->endpoint . $api_term_string;
    }    

    private function getUrlParams(): string {

        $config = $this->api_config;
        $api_limit_string = '&'.$config->limit.'=20';
        $api_token_string = '';

        if(!empty($this->api_limit) && is_numeric($this->api_limit) && $this->api_limit < 20 && $this->api_limit > 0) {
            $api_limit_string = "&" . $config->limit.'='.$this->api_limit;
        }

        if($this->api_key) {
            $api_token_string = ($this->api_config->term == "" ? "?" : "&") . $config->token . '=' . $this->api_key;
        } 


        return $api_token_string . $api_limit_string;
    }


   /**
    * Generates config description
    *
    * @return false|string
    * @since 1.0.0
    *
    */
    private function getApiLogoAsBase64() {
        $path = $this->getSafePath(__DIR__ . "/sources/img/" . $this->api_source . ".svg");
        $content = file_get_contents($path);
        
        if($content) {
            return base64_encode($content);
        }

        return false;
    }

   /**
     * Renders the module
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function renderModule(): void {

        if($this->isSourceValid) {
            $this->setLanguageStrings();
            $this->includeJavascript();
            $this->includeCSS();
        }

    }

   /**
     * Set Language Strings
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function setLanguageStrings(): void {

        $this->lang = array(
            "aac_status_default" => $this->tt("aac_status_default"),
            "aac_status_is_loading" => $this->tt("aac_status_is_loading"),
            "aac_status_is_not_listed" => $this->tt("aac_status_is_not_listed"),
            "aac_status_is_not_listed_ca" => $this->tt("aac_status_is_not_listed_ca"),
            "aac_status_is_listed" => $this->tt("aac_status_is_listed"),
            "aac_status_is_valid" => $this->tt("aac_status_is_valid"),
            "aac_status_is_custom" => $this->tt("aac_status_is_custom"),
            "aac_reset_address" => $this->tt("aac_reset_address"),
            "alert_no_selection" => $this->tt("alert_no_selection"),
            "cam_title" => $this->tt("cam_title"),
            "cam_field_street" => $this->tt("cam_field_street"),
            "cam_field_number" => $this->tt("cam_field_number"),
            "cam_field_city" => $this->tt("cam_field_city"),
            "cam_field_code" => $this->tt("cam_field_code"),
            "cam_field_country" => $this->tt("cam_field_country"),
            "cam_field_add_note" => $this->tt("cam_field_add_note"),
            "cam_button_add" => $this->tt("cam_button_add"),
            "cam_button_cancel" => $this->tt("cam_button_cancel")
        );

    }

   /**
     * Maps Results via Ajax Request
     *
     * @param string $source
     * @param object $results
     * @since 1.0.0
     *
     * @return void
     */
    public function mapResults($source, $results): void {

        header('Content-Type: application/json; charset=UTF-8');
        //$data = json_decode($results);

        //  Call mapper method by source
        $response = $this->getMappedResultsBySource($source, $results);        
        echo json_encode($response);
    }

   /**
     * Get Mapped Results by Source
     *
     * @param string $identifier
     * @param object $data
     * 
     * @since 1.0.0
     *
     */
    private function getMappedResultsBySource($identifier, $data) : ?array {

        //  Replace dots with underscores
        $class = str_replace(".", "_", $identifier);

        //  Get output format from settings
        $format = $this->getProjectSetting("output-format");

        //  Include class if not exists
        $path = $this->getSafePath( __DIR__ . "/sources/" . $class . ".source.php");
        if (!class_exists($class)) include_once( $path );

        //  Dynamic class with namespaces and Argument Unpacking
        //  https://stackoverflow.com/a/30647705/3127170
        $class_with_namespace = "STPH\\addressAutoComplete\\".$class;
        $arrayOfConstructorParameters = array($data, $format);
        
        return ( new $class_with_namespace(...$arrayOfConstructorParameters) )->getMappedResults();        

    }
 
   /**
     * Include JavaScript files
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function includeJavascript(): void {
        ?>
        <script src="<?php print $this->getUrl('js/main.js'); ?>"></script>
        <script> 
            $(function() {
                $(document).ready(function(){
                    STPH_addressAutoComplete.base_url = '<?= $this->getBaseUrl() ?>';
                    STPH_addressAutoComplete.endpoint_url = '<?= $this->getEndpointUrl() ?>';
                    STPH_addressAutoComplete.url_params = '<?= $this->getUrlParams() ?>';
                    STPH_addressAutoComplete.source_identifier = '<?= $this->api_config->identifier ?>';
                    STPH_addressAutoComplete.hasSecondaryAction = <?= json_encode($this->api_config->secondary) ?>;

                    STPH_addressAutoComplete.target_field = '<?= $this->target_field ?>';
                    STPH_addressAutoComplete.target_advanced = <?= json_encode($this->target_advanced) ?>;
                    STPH_addressAutoComplete.target_meta = '<?= $this->target_meta ?>';

                    STPH_addressAutoComplete.base64_logo = '<?= $this->getApiLogoAsBase64() ?>'
                    STPH_addressAutoComplete.requestHandlerUrl = '<?= $this->getUrl("requestHandler.php") ?>';
                    STPH_addressAutoComplete.advancedSave = <?= json_encode($this->isEnabledAdvancedSave) ?>;
                    STPH_addressAutoComplete.customAddress = <?= json_encode($this->isEnabledCustomAddress) ?>;
                    STPH_addressAutoComplete.customAddressModalUrl = '<?= $this->getUrl("customAddressModal.html") ?>';

                    STPH_addressAutoComplete.lang = <?= json_encode($this->lang) ?>;
                    STPH_addressAutoComplete.debug = <?= json_encode($this->debug) ?>;
                    STPH_addressAutoComplete.init();
                })
            });
        </script>
        <?php

        if($this->api_config->secondary) {            
             //  Replace dots with underscores
            $func_name = str_replace(".", "_", $this->api_config->identifier);
            ?>
            <script src="<?php print $this->getUrl('js/secondary/'.$func_name.'.js'); ?>"></script>
            <?php
        }
    }
    

    
   /**
     * Include Style files
     *
     * @since 1.0.0    
     *
     * @return void
     */
    private function includeCSS(): void {
        ?>
        <link rel="stylesheet" href="<?= $this->getUrl('style.css')?>">
        <?php
    }
    
}