<?php

class enrollment_model {
    /**
     *
     * @var array(semester) 
     */
    public $semesters;
    /**
     *
     * @var array(course) 
     */
    public $courses;
    /**
     *
     * @var array(student) 
     */
    public $students;
    
    /**
     *
     * @var group[]
     */
    public $groups;
    
    /**
     *
     * @var stdClass holds DB records of users 
     * users are considered active if they occur 
     * in mdl_log as having done anything in a course.
     */
    public $active_users;
    //table abstractions
//    public $ues_sections;
//    public $ues_courses;
//    public $ues_students;
//    public $ues_semesters;
//    public $mdl_courses;
//    public $mdl_logs;
//    public $mdl_users;
 
    public function __construct(){
        $this->semesters = self::get_active_ues_semesters();
        assert(!empty($this->semesters));
        $this->courses   = self::get_all_courses(array_keys($this->semesters));
    }
    
    public static function get_all_courses($semesterids){
        global $DB;
        $sql = sprintf("SELECT 
                    usect.id            AS ues_sectionid,
                    usect.sec_number    AS ues_sections_sec_number,  
                    usect.idnumber      AS idnumber,
                    c.id                AS mdl_courseid,
                    c.shortname         AS mdl_course_shortname,
                    usem.id             AS ues_semesterid,
                    ucourse.department  AS ues_course_department,
                    ucourse.cou_number  AS ues_cou_number
                FROM {enrol_ues_sections} usect
                INNER JOIN {enrol_ues_courses} ucourse
                    on usect.courseid = ucourse.id
                INNER JOIN {enrol_ues_semesters} usem
                    on usect.semesterid = usem.id
                INNER JOIN {course} c
                    on usect.idnumber = c. idnumber
                WHERE 
                    usem.id IN(%s)
                AND 
                    usect.idnumber <> ''",
                implode(',',$semesterids));
        //verify courses
        $courses = array();
        
        $rows = $DB->get_records_sql($sql);
        foreach($rows as $row){
            $course = new course();
            
            //build the course obj
            $mc                 = new mdl_course();
            $mc->id             = $row->mdl_courseid;
            $mc->idnumber       = $row->idnumber;
            $course->mdl_course = $mc;
            
            //build the ues section object
            $usect              = new ues_sections_tbl();
            $usect->id          = $row->ues_sectionid;
            $usect->idnumber    = $row->idnumber;
            $usect->sec_number  = $row->ues_sections_sec_number;
            $usect->semesterid  = $row->ues_semesterid;
            $course->ues_section= $usect;

            $courses[$mc->id]   = $course;
        }
        
        return $courses;
        
    }
    
    public function get_groups_with_students(){
        $sql = sprintf("SELECT
                    CONCAT(g.id,u.idnumber) AS userGroupId,
                    c.id                    AS courseid,
                    g.id                    AS groupId,
                    u.idnumber              AS studentId,
                    u.id                    AS userid,
                    gm.id                   AS gmid,
                    NULL AS extensions
                FROM {course} AS c

                    INNER JOIN {context} AS ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50                    
                    INNER JOIN {groups} AS g ON c.id = g.courseid                    
                    INNER JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
                    INNER JOIN {user} AS u ON u.id = ra.userid AND ra.roleid IN (5)
                    INNER JOIN {groups_members} AS gm ON g.id = gm.groupid AND u.id = gm.userid
                    
                WHERE c.id IN(%s)",
                implode(',',array_keys(self::get_all_courses(array_keys($this->semesters))))
                );
        
        
        global $DB;
        $rows = $DB->get_records_sql($sql);
        
        if(!isset($this->groups)){
            $this->groups = array();
        }
        foreach($rows as $row){

                //make a group member
                $mgm_gm = mdl_group_member::instantiate(array(
                    'id'        =>$row->gmid,
                    'userid'    =>$row->userid,
                    'groupid'   =>$row->groupid
                ));
                
                $mgm_mu = mdl_user::instantiate(array(
                    'id'        =>$row->userid,
                    'idnumber'  =>$row->studentid
                ));
                
                $gm = group_member::instantiate(array(
                    'mdl_user'  => $mgm_mu,
                    'mdl_group_member' => $mgm_gm
                ));

            if(!array_key_exists($row->groupid,$this->groups)){
                //make the group object and constituents
                $mg = mdl_group::instantiate(array(
                    'id'        =>$row->groupid,
                    'courseid'  =>$row->courseid,
                    ));
                
                $g = group::instantiate(array(
                    'mdl_group'     => $mg, 
                    'group_members' => array($gm->mdl_group_member->id=>$gm)
                    ));
                $this->groups[$row->groupid] = $g;
            }
            
            $this->groups[$row->groupid]->group_members[$gm->mdl_group_member->id] = $gm;
      

        }
        
        return $this->groups;
    }
    
    public function get_active_students($start,$end){
        assert(!empty($this->semesters));
        $active_users = $this->get_active_users($start,$end);
        $datarows = $this->get_semester_data(array_keys($this->semesters),
                array_keys($active_users));
        
        if(empty($this->students)){
            $this->students = array();
        }
        
        foreach($datarows as $row){
            
            $ues_course              = new ues_courses_tbl();
            $ues_course->cou_number  = $row->cou_number;
            $ues_course->department  = $row->department;

            $ues_section             = new ues_sections_tbl();
            $ues_section->sec_number = $row->sectionid;
            $ues_section->id         = $row->ues_sectionid;
            $ues_section->semesterid = $row->semesterid;

            $mdl_course              = new mdl_course();
            $mdl_course->id          = $row->mdl_courseid;

            $course                  = new course();
            $course->mdl_course      = $mdl_course;
            $course->ues_course      = $ues_course;
            $course->ues_section     = $ues_section;
            
            if(!array_key_exists($row->studentid, $this->students)){
                $s = new mdl_user();
                $s->id = $row->studentid;
                
                $student = new student();
                $student->mdl_user = $s;
                
                $this->students[$student->mdl_user->id] = $student;
            }
            
            $this->students[$student->mdl_user->id]->courses[$course->mdl_course->id] = $course;
            
        }

        return $this->students;
    }

    /**
     * for the input semesters, this method builds an 
     * internal tree-like data structure composed of 
     * nodes of type semester, section, user.
     * Note that this tree contains only enrollment, no timespent data.
     * 
     *  
     * @see get_active_ues_semesters
     */
    public function build_enrollment_tree(){
        
        
        assert(!empty($this));

        //define the root of the tree
        $tree = $this->enrollment;

        //put enrollment records into semester arrays
        $enrollments = $this->get_semester_data(array_keys($this->enrollment->semesters));
        //@TODO what hapens if this is empty???
        if(empty($enrollments)){
            return false;
        }

        
        //walk through each row returned from the db @see get_semester_data
        foreach($enrollments as $row => $e){
            
            //in populating the first level, above, we should have already 
            //allocated an array slot for every possible value here
            assert(array_key_exists($e->semesterid, $tree->semesters));

                $semester = $tree->semesters[$e->semesterid];
                if(empty($semester->courses)){
                    $semester->courses = array();
                }
                
                if(!array_key_exists($e->sectionid, $semester->courses)){
                    //add a new section to the semester node's sections array
                    
                    $ues_course = new stdClass();
                    $ues_course->cou_number     = $e->cou_number;
                    $ues_course->department  = $e->department;
                    
                    $ues_section = new stdClass();
                    $ues_section->sec_number = $e->sectionid;
                    $ues_section->id         = $e->ues_sectionid;
                    $ues_section->semesterid = $e->semesterid;

                    $mdl_course = new stdClass();
                    $mdl_course->id    = $e->mdl_courseid;

                    $ucourse = ues_courses_tbl::instantiate($ues_course);
                    
                    $usect = ues_sections_tbl::instantiate($ues_section);
                    $mdlc = mdl_course::instantiate($mdl_course);
                    
                    $course_params = array('ues_course' => $ucourse, 
                        'ues_section' => $usect,
                        'mdl_course' => $mdlc);
                    
                    $semester->courses[$e->ues_sectionid] = course::instantiate($course_params);
                    
                    
                    //@TODO refactor to use student, not old user class
                    //add this row's student as the next element 
                    //of the current section's users array
                    $user     = new user();
                    $user->id = $e->studentid;
                    $user->apid = $e->apid;
//                    $user->last_update = $e->last_update;
                    $section->users[$e->studentid] = $user;
                }else{
                    //the section already exists, so just add the user 
                    //to the semester->section->users array
                    $user = new user();
                    $user->id = $e->studentid;
                    $user->apid = $e->apid;
//                    $user->last_update = $e->last_update;
                    $semester->sections[$e->sectionid]->users[$e->studentid] = $user;
                }
        }
//        print_r($tree);
        return $tree;
    }
    
    /**
     * utility function that takes in an array of [active] semesterids and queries
     * the db for enrollment records on a per-[ues]section basis;
     * The result set of this query is limited by the return value of 
     * @see get_active_users
     * @global type  $DB
     * @param  array $semesterids  integer semester ids, presumably for active semesters
     * @return array stdClass | false
     */
    public function get_semester_data($semesterids, $userids){
        global $DB;

        //use the idnumbers of the active users to reduce the number of rows we're working with;
        if(!$userids){
            add_to_log(1, 'ap_reports', 'no active users');
            return false;
        }
        
        
        $sql = vsprintf(
            "SELECT
                CONCAT(usem.year, '_', usem.name, '_', uc.department, '_', uc.cou_number, '_', us.sec_number, '_', u.idnumber) AS enrollmentId,
                u.id AS studentId, 
                usem.id AS semesterid,
                usem.year,
                usem.name,
                uc.department,
                uc.cou_number,
                us.sec_number AS sectionId,
                c.id AS mdl_courseid,
                us.id AS ues_sectionid,
                'A' AS status,
                CONCAT(usem.year, '_', usem.name, '_', uc.department, '_', uc.cou_number, '_', us.sec_number) AS uniqueCourseSection
            
            FROM {course} AS c
                INNER JOIN {context}                  AS ctx  ON c.id = ctx.instanceid
                INNER JOIN {role_assignments}         AS ra   ON ra.contextid = ctx.id
                INNER JOIN {user}                     AS u    ON u.id = ra.userid
                INNER JOIN {enrol_ues_sections}       AS us   ON c.idnumber = us.idnumber
                INNER JOIN {enrol_ues_students}       AS ustu ON u.id = ustu.userid AND us.id = ustu.sectionid
                INNER JOIN {enrol_ues_semesters}      AS usem ON usem.id = us.semesterid
                INNER JOIN {enrol_ues_courses}        AS uc   ON uc.id = us.courseid
                
            WHERE 
                ra.roleid IN (5)
                AND usem.id in(%s)
                AND ustu.status = 'enrolled'
                AND u.id IN(%s)
            ORDER BY uniqueCourseSection"
                , array(implode(',',$semesterids)
                        , implode(',', $userids))
                );
        
        $rows = $DB->get_records_sql($sql);
        
        return count($rows) > 0 ? $rows : false;
    }
    
    /**
     * Get a list of all semesters 
     * where {classes_start < time() < grades_due}
     * 
     * @return array stdClass of active semesters
     */
    public static function get_active_ues_semesters($time=null){
        global $DB;
        $time = isset($time) ? $time : time();
        $sql = vsprintf("SELECT 
                                * 
                            FROM 
                                {enrol_ues_semesters}
                            WHERE 
                                classes_start <= %d 
                            AND 
                                grades_due >= %d"
                        , array($time,$time));
        $semesters = $DB->get_records_sql($sql);
//die(print_r($sql));
        assert(count($semesters) > 0);
        $s = array();
        foreach($semesters as $semester){
            $obj = semester::instantiate(array('ues_semester'=>$semester));
            $s[$obj->ues_semester->id] = $obj;
        }

        return $s;
    }
    
    /**
     * this function is a utility method that helps optimize the overall 
     * routine by limiting the number of people we check;
     * 
     * We do this by first getting a collection of potential users from current enrollment;
     * Then, limit that collection to include only those users who have registered activity in the logs
     * 
     */
    public function get_active_users($start, $end){
       global $DB;
       
       //get one userid for anyone in the mdl_log table that has done anything
       //in the temporal bounds
       //get, also, the timestamp of the last time they were included in this 
       //scan (so we keep a contiguous record of their activity)
       $sql =  vsprintf("SELECT DISTINCT u.id
                FROM 
                    {log} log 
                        join 
                    {user} u 
                        on log.userid = u.id 
                WHERE 
                    log.time > %s
                AND 
                    log.time < %s;",array($start,$end));
       $this->active_users = $DB->get_records_sql($sql);
       
       return count($this->active_users) > 0 ? $this->active_users : false;
    }
 
}


?>