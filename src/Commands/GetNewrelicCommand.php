<?php
/**
 * This command will fetch an new relic overview of the project 
 * in a specific environment
 */
namespace Pantheon\TerminusGetNewrelic\Commands;

use Pantheon\Terminus\Commands\Site\SiteCommand;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Symfony\Component\Filesystem\Filesystem;
use League\CLImate\CLImate;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

class GetNewrelicCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;

    /**
     * Object constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    private $new_relic_monitoring = [
        'overview',
        'plan',
    ];

    /**
     * Pull sites new-relic data within org
     *
     * @command newrelic-data:org
     *
     * @option overview
     *
     * @filter-output
     *
     * @field-labels
     *     Site: Site
     *     Service Level: Service Level
     *     Framework: Framework
     *     Site Created: Site Created
     *     Newrelic: Newrelic
     *     Dashboard URL: Dashboard URL
     *
     * @return RowsOfFields
     */
    public function org($org_id, $plan = null, 
        $options = ['overview' => false, 
                'all' => false, 
                'team' => false,
                'exclude-free-plan' => false, 
                'owner' => null]
    ) {
        if(!empty($org_id)) {
            $pro = array();
            $basic = array();
            $free = array();
            $business = array();
            $elite = array();
            $performance = array();
            $preloader = 'Loading.';
            $counter = 0;

        
            date_default_timezone_set("Asia/Manila");
            $ds = date("h:i:sa");

            $this->sites()->fetch(
                [
                    'org_id' => isset($org_id) ? $org_id : null,
                    'team_only' => isset($options['team']) ? $options['team'] : false,
                ]
            );

            if (isset($options['owner']) && !is_null($owner = $options['owner'])) {
                if ($owner == 'me') {
                    $owner = $this->session()->getUser()->id;
                }
                $this->sites->filterByOwner($owner);
            }

            $site_ids = $this->sites->filter(function ($site) {return $site->get('service_level') != 'free';})->ids();

            if (empty($site_ids)) {
                $this->log()->notice('You have no sites.');
            }

            $count = count($site_ids);
            $str_format = "";
            $exclude_free = 'noplan';
            if($options['exclude-free-plan']) {
                $exclude_free = 'free';
            }
            $this->log()->notice('Fetching Newrelic data for {count} sites. This may take a while...', compact('count'));
           
            foreach ($site_ids as $site_id)
            {
                $site = $this->sites->get($site_id);
                $serialized_site = $site->serialize();
                $counter++;
                if ($environments = $this->getSite($serialized_site['name'])->getEnvironments()->serialize()) {
                    foreach ($environments as $environment) 
                    {
                        if ($environment['id'] == 'dev' AND !$options['overview'] ) { 
                            $site_env = $serialized_site['name'] . '.' . $environment['id'];
                            list(, $env) = $this->getSiteEnv($site_env);
                            $env_id = $env->getName();
                            $newrelic = $env->getBindings()->getByType('newrelic');
                            $nr_data = array_pop($newrelic);
                            $nr_status = empty($nr_data) ? "Disabled" : "Enabled";
                            $dash_path = 'https://dashboard.pantheon.io/sites/';

                            if(empty($nr_data) OR $options['all']) {
                                $appserver = $env->getBindings()->getByType('appserver');
                                $appserver_data = array_pop($appserver);
                                $dash_link = empty($appserver_data) ? "--" : $dash_path . $appserver_data->get('site');

                                $service_level = $serialized_site['service_level'];
                                if (!$service_level) {
                                    $service_level = $site->get('service_level');
                                }
                                $data = array( "Site" => $serialized_site['name'],
                                    "Service level" => $serialized_site['service_level'],
                                    "Framework"  => $serialized_site['framework'],
                                    "Site created" => $serialized_site['created'],
                                    "Newrelic" => $nr_status,
                                    "Dashboard URL" => $dash_link);

                                if($nr_status == 'Disabled' && $service_level != "free") {
                                    $str_format .= $serialized_site['name'] .",";
                                }

                                    switch ($service_level) 
                                    {
                                    case "free":
                                        $free[] = $data;
                                        break;

                                    case "basic":
                                    case "basic_small":
                                        $basic[] = $data;
                                        break;

                                    case "pro":
                                        $pro[] = $data;
                                        break;
                                    
                                    case "business":
                                    case "business_xl":
                                    
                                        $business[] = $data;
                                        break;

                                    case "performance_small":
                                    case "performance_medium":
                                    case "performance_large":
                                    case "performance_xlarge":
                                        $performance[] = $data;
                                        break;

                                    case "elite":
                                    case "elite_starter":
                                    case "elite_plus":
                                    case "elite_super":
                                    case "elite_max":
                                        $elite[] = $data;
                                        break;
                                    }
                            }
                        }
                                                                                        
                        if ($environment['id'] == 'live' AND $options['overview']) { 
                            $site_env = $serialized_site['name'] . '.' . $environment['id'];
                            list(, $env) = $this->getSiteEnv($site_env);
                            $env_id = $env->getName();
                            $newrelic = $env->getBindings()->getByType('newrelic');
                            $nr_data = array_pop($newrelic);
                            //print_r($nr_data);
                            
                            if(!empty($nr_data) OR $options['all']) {
                                $api_key = $nr_data->get('api_key');
                                $pop = $this->fetch_newrelic_data($api_key, $env_id);
                                if($pop) {
                                    $items[] = $pop;
                                }
                            }
                        }                                                                
                    }
                
                }
                $this->log()->notice("Fetched Newrelic data for {counter} out of {count} sites.", compact('counter', 'count'));
            }

            if (empty($items) AND !isset($items)) {
                $this->log()->notice("Sites with No New Relic: " . substr($str_format, 0, -1));

                $site_plans = array('free', 'basic', 'business', 'performance', 'elite');
                $items = [];
                
                foreach($site_plans as $plan)
                {
                    if (!empty($$plan)) {
                        $items = array_merge($items, $$plan);
                    }
                }
                $table = new RowsOfFields($items);
            } else 
            {
                $items = $this->multi_sort($items);
                $table = new RowsOfFields($items);
            }
            
            $de=date("h:i:sa");
            $this->log()->notice('Perf Audit Completed in: from '. $ds .' to ' . $de);
            return $table;
        }
    }


    /**
     * Pull new-relic data per site
     *
     * @command newrelic-data:site
     * @filter-output
     *
     * @field-labels
     *     Name: Name
     *     Appserver Response Time: Appserver Response Time
     *     Appserver Throughput: Appserver Throughput
     *     Error Rate: Error Rate
     *     Apdex Target: Apdex Target
     *     Browser Load Time: Browser Load Time
     *     Avg Page Load Time: Avg Page Load Time
     *     Number of Hosts: Number of Hosts
     *     Number of Instance: Number of Instance
     *     Health Status: Health Status
     *
     * @return RowsOfFields
     */
    public function site($site_env_id, $plan = null,
        $options = ['all' => false, 'overview' => false]
    ) {
        // Get env_id and site_id.
        list($site, $env) = $this->getSiteEnv($site_env_id);
        $env_id = $env->getName();
        $siteInfo = $site->serialize();
        $site_id = $siteInfo['id'];
        $newrelic = $env->getBindings()->getByType('newrelic');
        $nr_data = array_pop($newrelic);

        if(!empty($nr_data)) {
            $api_key = $nr_data->get('api_key');
            $pop = $this->fetch_newrelic_data($api_key, $env_id);
            if(isset($pop)) {
                $items[] = $pop;
                $table = new RowsOfFields($items);
                return $table;
            }
        }

    }


    /**
    * Pull new-relic data info per site
    *
    * @command newrelic-data:info
    */
    public function info($site_env_id, $plan = null, 
        $options = ['custom_name' => false]
    ) {

        // Get env_id and site_id.
        list($site, $env) = $this->getSiteEnv($site_env_id);
        $env_id = $env->getName();

        $siteInfo = $site->serialize();
        $site_id = $siteInfo['id'];
        $newrelic = $env->getBindings()->getByType('newrelic');

        $nr_data = array_pop($newrelic);
        if(!empty($nr_data)) {
            $api_key = $nr_data->get('api_key');
            $nr_id = $nr_data->get('account_id');
            #echo $site_name. "<<<<";
             
            $pop = $this->fetch_newrelic_info($api_key, $nr_id, $env_id);
            if(isset($pop)) {
                $items[] = $pop;
                return $items;
            }
        }
        return false;
    }

    /**
    * Color Status based on New-relic
    */
    public function HealthStatus($color) 
    {
        switch ($color) 
        {
        case "green":
            return "Healthy Condition";
                break;
        case "red":
            return "<blink><red>Critical Condition</red></blink>";
                break;
        case "yellow":
            return "<yellow>Warning Condition</yellow>";
                break;
        case "gray":
            return "Not Reporting";
                break;
        default:
            return "Unknown";
                break;
        }
    }

     /**
      * Object constructor
      */

    public function CallAPI($method, $url, $api_key, $data = false) 
    {
        $header[] = 'X-Api-Key:' . $api_key;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);


        $data = curl_exec($ch);
        curl_close($ch);;

        return $data;
    }

    public function multi_sort($items) 
    {
        foreach ($items as $key => $row) 
        {
            if(isset($row['Appserver Response Time'])) {
                $resp[$key]  = $row['Appserver Response Time'];
            }
        }
        // Sort the items with responsetime descending, throughput ascending
        // Add $items as the last parameter, to sort by the common key
        array_multisort($resp, SORT_DESC, $items);
        return $items;
    }

    public function check_array_keys($obj, $status, $reporting) 
    {
        $arr_components = array("response_time" => "Appserver Response Time",
                                "throughput" => "Appserver Throughput",
                                "error_rate" => "Error Rate",
                                "apdex_target" => "Apdex Target",
                                "browser_loadtime" => "Browser Load Time",
                                "avg_browser_loadtime" => "Avg Page Load Time",
                                "host_count" => "Number of Hosts",
                                "instance_count" => "Number of Instance");

        $items = array( "Name" => $obj['name'],
                        
                         "Appserver Response Time" => "--",
                         "Appserver Throughput" => "--",
                         "Error Rate" => "--",
                         "Apdex Target" => "--",
                         "Browser Load Time" => "--",
                         "Avg Page Load Time" => "--",
                         "Number of Hosts" => "--",
                         "Number of Instance" => "--",
                         "Health Status" => $status);

        if((!empty($reporting) OR $reporting != 'Not Reporting') AND isset($obj['application_summary'])) {
            $sum_obj = $obj['application_summary'];
            foreach ($arr_components as $key => $val) 
            {
                if (array_key_exists($key, $sum_obj)) {
                    if($key == 'response_time'){
                        $val = 'Appserver Response Time';
                    }
                    if($key == 'throughput'){
                        $val = 'Appserver Throughput';
                    }
                    $items[$val] = $sum_obj[$key];
                }
                if(isset($obj['end_user_summary'])){
                    $end_user_obj = $obj['end_user_summary'];
                    if (array_key_exists($key, $end_user_obj)) {
                        if($key == 'response_time'){
                            $val = 'Browser Load Time';
                        }
                        if($key == 'throughput'){
                            $val = 'Avg Page Load Time';
                        }
                        $items[$val] = $end_user_obj[$key];
                    }
                }
                
            }
        }

        return $items;
    }

    public function fetch_newrelic_data($api_key, $env_id) 
    {
        $url =  'https://api.newrelic.com/v2/applications.json';
        $result = $this->CallAPI('GET', $url, $api_key, $data = false);
        $obj_result = json_decode($result, true);

        if(isset($obj_result['applications'])) {
            foreach($obj_result['applications'] as $key => $val) {
                $url =  "https://api.newrelic.com/v2/applications/" . $val['id'] . ".json";
                $myresult = $this->CallAPI('GET', $url, $api_key, $data = false);
                $item_obj = json_decode($myresult, true);
                if(strstr($item_obj['application']['name'], $env_id)) {
                    $obj = $item_obj['application'];
                    $status = $this->HealthStatus($obj['health_status']);
                    $reporting = $this->HealthStatus($obj['reporting']);
                    return $this->check_array_keys($obj, $status, $reporting);
                }
            }
        }
        return false;
    }


    public function fetch_newrelic_info($api_key, $nr_id, $env_id) 
    {
       
        $url =  'https://api.newrelic.com/v2/applications.json';
        $count=0;

        $result = $this->CallAPI('GET', $url . "?filter[name]=+(live)", $api_key, $data = false);
        $obj_result = json_decode($result, true);
        if(isset($obj_result['applications'])) {
            $count = count($obj_result['applications']);
            $this->log()->notice($count);
            foreach($obj_result['applications'] as $key => $val) 
            {
                $isMatched = strstr(strtolower($val['name']), '(' . strtolower($env_id) . ')');
                if($isMatched != "") {
                    $url =  "https://api.newrelic.com/v2/applications/" . $val['id'] . ".json";
                    $myresult = $this->CallAPI('GET', $url, $api_key, $data = false);
                    return json_encode(array_merge(json_decode($myresult, true), array("api_key" => $api_key, "nr_id" => $nr_id)));
                }
            }
        }
        return false;
    }

}
