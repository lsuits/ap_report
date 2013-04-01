<?php

defined('MOODLE_INTERNAL') or die();
global $CFG;
$plugin = 'local_ap_report';

$_s = function($key,$a=null) use ($plugin){
    return get_string($key, $plugin, $a=null);
};


//echo $_s('pluginname_desc');
//die();

//global $ADMIN;
if ($hassiteconfig) {

    $settings = new admin_settingpage('local_ap_report', 'AP Reports');
    
    
    $a = new stdClass();
    $repro_url = new moodle_url('/local/ap_report/reprocess.php');
    $a->url = $repro_url->out(false);
    
    
//    $reproc = html_writer::tag('a', 'Reprocess', array('href'=>$a->url));
    
    $settings->add(
            new admin_setting_heading(
                    'apreports_settings',
                    'Enrollment Report Settings',
                    get_string('pluginname_desc',$plugin, $a->url)
                    ));

//    $settings->add(
//            new admin_setting_configcheckbox(
//                    'local_apreport_with_cron',
//                    $_s('with_cron'),
//                    $_s('with_cron_desc'),
//                    0
//                    ));
    
    $stop  = $CFG->apreport_job_complete;
    $start = $CFG->apreport_job_start;
    
    if(!isset($stop) and isset($start)){
        $compl_status = sprintf("FAILURE! Last job began at %s and has not recorded a completion timestamp"
                );
    }elseif(!isset($stop) and !isset($start)){
        $compl_status = sprintf("There is no evidence that this job has ever run");
        
    }elseif(isset($stop) and !isset($start)){
        $compl_status = sprintf("ERROR: job completion time is set as %s, but no start time exists in the db.", $stop);
    }elseif($stop > $start){
        $stop  = $stop;
        $start = $start;
        
        $compl_status = sprintf("Last Run began at %s and completed at %s",
                strftime('%F %T', $stop), 
                strftime('%F %T', $start)
                );
    }else{
        $compl_status = sprintf("FAILURE! Last job began at %s and has not recorded a completion timestamp",
                strftime('%F %T', $start)
                );
    }
    $settings->add(
            new admin_setting_heading(
                    'apreport_last_completion', 
                    'Completion Status', $compl_status
                    ));
    
    $settings->add(
            new admin_setting_configtime(
                    'apreport_daily_run_time_h',
                    'apreport_daily_run_time_m',
                    $_s('daily_run_time'),
                    $_s('daily_run_time_dcr'),
                    array('h'=>0, 'm'=>5)
            ));
    
//    $settings->add(
//            new admin_setting_configtime(
//                    'local_apreport_range_start_h',
//                    'local_apreport_range_start_m',
//                    $_s('range_start'),
//                    $_s('range_start_dcr'),
//                    array('h'=>0, 'm'=>5)
//                    ));
//    $settings->add(
//            new admin_setting_configtime(
//                    'local_apreport_range_end_h',
//                    'local_apreport_range_end_m',
//                    $_s('range_end'),
//                    $_s('range_end_dcr'),
//                    array('h'=>0, 'm'=>5)
//            ));
    
    $settings->add(
            new admin_setting_configtext(
                    'local_apreport_range_start',
                    $_s('range_start'),
                    $_s('range_start_dcr'),
                    PARAM_INT
                    )
            );
    
    $settings->add(
            new admin_setting_configtext(
                    'local_apreport_range_end',
                    $_s('range_end'),
                    $_s('range_end_dcr'),
                    PARAM_INT
                    )
            );
    
    
    
    $settings->add(
            new admin_setting_configtext(
                    'apreport_enrol_xml', 
                    'Filename', 
                    'give a name for the xml file (the extension is not required here).',
                    'enrollment',
                    PARAM_FILE)
            );
    
    $ADMIN->add('localplugins', $settings);

    
    
}

//class admin_setting_configtext_hour extends admin_setting_configtext{
//    public function validate($data){
//        if(parent::validate($data)){
//            if($data <=23 and $data >=0){
//                return true;
//            }else{
//                return false;
//            }
//        }
//    }
//}

?>
