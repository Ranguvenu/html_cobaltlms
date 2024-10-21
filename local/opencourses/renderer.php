<?php

/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package ODL
 * @subpackage local_courses
 */

// use core_component;
defined('MOODLE_INTERNAL') || die;

class local_opencourses_renderer extends plugin_renderer_base {
     /**
     * [render_classroom description]
     * @method render_classroom
     * @param  \local_classroom\output\classroom $page [description]
     * @return [type]                                  [description]
     */
    public function render_opencourses(\local_opencourses\output\courses $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_opencourses/opencourses', $data);
    }
    /**
     * [render_form_status description]
     * @method render_form_status
     * @param  \local_classroom\output\form_status $page [description]
     * @return [type]                                    [description]
     */
    public function render_form_status(\local_opencourses\output\form_status $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_opencourses/form_status', $data);
    }

    /**
     * Display the avialable courses
     *
     * @return string The text to render
     */
    public function get_catalog_opencourses($filter = false,$view_type='card') {

        $systemcontext = context_system::instance();
        $status = optional_param('status', '', PARAM_RAW);
        $costcenterid = optional_param('costcenterid', '', PARAM_INT);
        $departmentid = optional_param('departmentid', '', PARAM_INT);
        $subdepartmentid = optional_param('subdepartmentid', '', PARAM_INT);

        $templateName = 'local_opencourses/catalog';
        $cardClass = 'col-md-6 col-12';
        $perpage = 12;
        if($view_type=='table'){
            $templateName = 'local_opencourses/catalog_table';
            $cardClass = 'tableformat';
            $perpage = 10;
        }
        if ($view_type == 'card') {
            $formattype_url = 'table';
            $display_text = get_string('listtype', 'local_courses');
            $display_texticon = get_string('listtypeicon', 'local_courses');
        } else {
            $formattype_url = 'card';
            $display_text = get_string('cardtype', 'local_courses');
            $display_texticon = get_string('cardtypeicon', 'local_courses');
        }
       $options = array('targetID' => 'manage_opencourses', 'perPage' => $perpage, 'cardClass' => 'col-md-3 col-12', 
                        'viewType' => $view_type);
        $options['methodName']='local_opencourses_opencourses_view';
        $options['templateName']= $templateName;
        $options = json_encode($options);
        $filterdata = json_encode(array('status' => $status, 'organizations'=>$costcenterid, 
                                        'departments' => $departmentid, 'subdepartment' => $subdepartmentid));
        $dataoptions = json_encode(array('contextid' => $systemcontext->id, 'status' => $status, 'costcenterid' => $costcenterid, 
                                         'departmentid' => $departmentid, 'subdepartment' => $subdepartmentid,'viewType' => $view_type));
        $context = [
                'targetID' => 'manage_opencourses',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];

        if($filter){
            return  $context;
        }else{

            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

    /**
     * Display the avialable categories list
     *
     * @return string The text to render
     */
    public function get_categories_list($filter = false) {
        $id = optional_param('id', 0, PARAM_INT);
        $systemcontext = context_system::instance();
    
        $options = array('targetID' => 'manage_categories','perPage' => 12, 
                         'cardClass' => 'col-md-3 col-sm-6', 'viewType' => 'card' );
        $options['methodName']='local_courses_categories_view';
        $options['templateName']='local_courses/categorylist';
        $options['parentid'] = $id;
        $options = json_encode($options);
        $filterdata = json_encode(array());
        $dataoptions = json_encode(array('contextid' => $systemcontext->id));
        $context = [
                'targetID' => 'manage_categories',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];
        if($filter){
            return  $context;
        } else {
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }       
    }

    /**
     * Renders html to print list of courses tagged with particular tag
     *
     * @param int $tagid id of the tag
     * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
     *             are displayed on the page and the per-page limit may be bigger
     * @param int $fromctx context id where the link was displayed, may be used by callbacks
     *            to display items in the same context first
     * @param int $ctx context id where to search for records
     * @param bool $rec search in subcontexts as well
     * @param array $displayoptions
     * @return string empty string if no courses are marked with this tag or rendered list of courses
     */
    public function tagged_courses($tagid, $exclusivemode = true, $ctx = 0, 
                                   $rec = true, $displayoptions = null, $count = 0, $sort='') {
        global $CFG, $DB, $USER;
        $systemcontext = context_system::instance();
        $userorg = array();
        $userdep = array();
        if ($count > 0)
        $sql =" select count(c.id) from {course} c ";
        else
        $sql =" select c.* from {course} c  ";
        $joinsql = $groupby = $orderby = '';
        if (!empty($sort) && $count == 0) {
          switch($sort) {
            case 'highrate':
            if ($DB->get_manager()->table_exists('local_rating')) {
              $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_courses' ";
              $groupby .= " group by c.id ";
              $orderby .= " order by AVG(rating) desc ";
            }
            break;
            case 'lowrate':
            if ($DB->get_manager()->table_exists('local_rating')) {
              $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_courses' ";
              $groupby .= " group by c.id ";
              $orderby .= " order by AVG(rating) asc ";
            }
            break;
            case 'latest':
            $orderby .= " order by c.timecreated desc ";
            break;
            case 'oldest':
            $orderby .= " order by c.timecreated asc ";
            break;
            default:
            $orderby .= " order by c.timecreated desc ";
            break;
            }
        }

        if(is_siteadmin()){
            $joinsql .= " JOIN {local_costcenter} AS co ON co.id = c.open_costcenterid
                         JOIN {course_categories} AS cc ON cc.id = c.category
                         where 1 = 1 ";
        } elseif(has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $joinsql .= " JOIN {local_costcenter} AS co ON co.id = c.open_costcenterid
                       JOIN {course_categories} AS cc ON cc.id = c.category
                       WHERE c.open_costcenterid = :usercostcenter";
        } elseif(has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
            $joinsql .= " JOIN {local_costcenter} AS co ON co.id = c.open_costcenterid
                       JOIN {course_categories} AS cc ON cc.id = c.category
                       WHERE c.open_costcenterid = :usercostcenter 
                       AND c.open_departmentid = :userdepartment";
        } else {
            $joinsql .= " JOIN {local_costcenter} AS co ON co.id = c.open_costcenterid
                       JOIN {course_categories} AS cc ON cc.id = c.category
                       WHERE c.open_costcenterid = :usercostcenter 
                       AND c.open_departmentid = :userdepartment";
        }

        if (!is_siteadmin()) {
            $userorg['usercostcenter'] = $USER->open_costcenterid;
            $userdep['userdepartment'] = $USER->open_departmentid;
        }

        $tagparams = array('tagid' => $tagid, 'itemtype' => 'courses', 'component' => 'local_courses');
        $params = array_merge($userorg, $userdep, $tagparams);

        $where = " AND c.id IN (SELECT t.itemid FROM {tag_instance} t 
                   WHERE t.tagid = :tagid AND t.itemtype = :itemtype AND t.component = :component)";

        if ($count > 0) {
            $records = $DB->count_records_sql($sql.$joinsql.$where, $params);
            return $records;
        } else {
            $records = $DB->get_records_sql($sql.$joinsql.$where.$groupby.$orderby, $params);
        }
        
        $tagfeed = new local_tags\output\tagfeed(array(), 'local_courses');
        $img = $this->output->pix_icon('i/course', '');
        foreach ($records as $key => $value) {
          $url = $CFG->wwwroot.'/course/view.php?id='.$value->id.'';
          $imgwithlink = html_writer::link($url, $img);
          $modulename = html_writer::link($url, $value->fullname);
          $coursedetails = get_course_details($value->id);
          $details = $this->render_from_template('local_courses/tagview', $coursedetails);
          $tagfeed->add($imgwithlink, $modulename, $details);
        }
        return $this->output->render_from_template('local_tags/tagfeed', $tagfeed->export_for_template($this->output));

    }
    public function get_parent_category_data($categoryid){
        global $DB;
        $category = $DB->get_record('course_categories', array('id' => $categoryid));
        $data = array();
        $data['category_name'] = strlen($category->name) > 20 ? substr($category->name, 0, 20).'...' : $category->name;
        $data['category_name_title'] = $category->name;
        $data['category_code'] = strlen($category->idnumber) > 20 ? substr($category->idnumber, 0, 20).'...' : $category->idnumber;
        $data['category_code_title'] = $category->idnumber;
        $categorycontext = \context_coursecat::instance($category->id);
        $data['courses'] = html_writer::link('javascript:void(0)', $category->coursecount, array('title' => '', 'alt' => '', 'class'=>'createcoursemodal', 'onclick' =>'(function(e){ require("local_courses/newcategory").courselist({contextid:'.$categorycontext->id.', categoryname: "'.$category->name.'", categoryid: "' . $category->id . '" }) })(event)'));
        $data['subcategory_count'] = $DB->count_records('course_categories', array('parent' => $categoryid)); 

        return $this->render_from_template('local_courses/parent_template', $data);
    }

  function display_course_enrolledusers($courseid){
    global $DB;
  
    $certificate_plugin_exist = \core_component::get_plugin_directory('tool', 'certificate');
    $systemcontext = \context_system::instance();
    if(is_siteadmin() || has_capability('enrol/manual:manage', $systemcontext)) {
                $enrolid = $DB->get_field('enrol', 'id', array('courseid' => $courseid, 'enrol' => 'manual'));
                $userenrollment = true;
        }
    $info = array();
    $info['enrolid'] = $enrolid;
    $info['courseid'] = $courseid;
    
    if($certificate_plugin_exist){
      $certificate = $DB->get_field('course', 'open_certificateid', array('id'=>$courseid));
      if($certificate){
        $info['added_certificate'] = true;
      }else{
        $info['added_certificate'] = false;
      }
    }
    
    return $this->render_from_template('local_courses/courseusersview', $info);
  }

  function get_course_enrolledusers ($dataobj) {
    global $DB, $USER, $OUTPUT, $CFG;

    $countsql = "SELECT COUNT(ue.id) ";

    $selectsql = "SELECT DISTINCT(u.id) as userid, ue.id, u.firstname, u.lastname, u.email, u.open_employeeid, 
            cc.timecompleted";
    $sql = " FROM {course} c
            JOIN {course_categories} cat ON cat.id = c.category
            JOIN {enrol} e ON e.courseid = c.id AND 
                        (e.enrol = 'manual' OR e.enrol = 'self') 
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
            JOIN {local_costcenter} lc ON lc.id = u.open_costcenterid
            JOIN {role_assignments} as ra ON ra.userid = u.id
            JOIN {context} AS cxt ON cxt.id=ra.contextid AND cxt.contextlevel = 50 AND cxt.instanceid=c.id
            JOIN {role} as r ON r.id = ra.roleid AND r.shortname = 'employee'
            LEFT JOIN {course_completions} as cc ON cc.course = c.id AND u.id = cc.userid 
            WHERE c.id = :courseid ";
   
    $params = array();
    $params['courseid'] = $dataobj->courseid;

    $systemcontext = \context_system::instance();

    if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
      $sql .= " AND c.open_costcenterid = :costcenterid ";
      $params['costcenterid'] = $USER->open_costcenterid;
    } else if (!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
      $sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid ";
      $params['costcenterid'] = $USER->open_costcenterid;
      $params['departmentid'] = $USER->open_departmentid;
    }

    if (!empty($dataobj->search)) {
      $concatsql = " AND ( CONCAT(u.firstname, ' ', u.lastname) LIKE '%".$dataobj->search."%' OR
                          u.open_employeeid LIKE '%".$dataobj->search."%' ) ";
    } else {
      $concatsql = '';
    }

    $courseusers = $DB->get_records_sql($selectsql.$sql.$concatsql , $params, $dataobj->start, $dataobj->length);
    $enrolleduserscount = $DB->count_records_sql($countsql.$sql.$concatsql , $params);
    $userslist = array();
    if ($courseusers) {
      $userslist = array();

      $enrolledcount = $enrolleduserscount;

      $certificate_plugin_exist = \core_component::get_plugin_directory('tool', 'certificate');

      if ($certificate_plugin_exist) {
        $cert_plugin_exists = true;
        $certificate = $DB->get_field('course', 'open_certificateid', array('id'=>$dataobj->courseid));
        if ($certificate) {
          $icon = '<i class="icon fa fa-download" aria-hidden="true"></i>';
          $certificate_added = true;
        } else {
          $certificate_added = false;
        }
      } else {
        $cert_plugin_exists = false;
      }

      foreach ($courseusers as $enroluser) {
        $userinfo = array();
        $userinfo[] = $enroluser->firstname.' '.$enroluser->lastname;
        $userinfo[] = $enroluser->open_employeeid;
        $userinfo[] = $enroluser->email;
        if ($enroluser->timecompleted) {
          $userinfo[] = get_string('completed', 'local_courses');
          $userinfo[] = \local_costcenter\lib::get_userdate('d/m/Y H:i a', $enroluser->timecompleted);
        } else {
          $userinfo[] = get_string('notcompleted', 'local_courses');
          $userinfo[] = 'N/A';
        }

        
        $get_enrolid = "";
        $get_enrolmentod = "";
        $sql = "SELECT ue.id, e.enrol FROM {user_enrolments} as ue
                JOIN {enrol} as e ON e.id = ue.enrolid 
                WHERE e.courseid = $dataobj->courseid AND ue.userid =$enroluser->userid ";
        $userenrolment = $DB->get_records_sql($sql);
        $enrolmethod = array();
        $enroll = array();
        foreach ($userenrolment as $userenrol) {
          $enroll[] = $userenrol->enrol;

         if (is_siteadmin() || (has_capability('local/courses:managecourses', context_system:: instance()))) {
         $icon = '<i class="icon fa fa-pencil" aria-hidden="true"></i>';
         $array = array('id'=>$dataobj->courseid, 'ue'=>$userenrol->id);
         $url = new moodle_url('editenrol.php', $array);
         $options = array('title'=>get_string('edit', 'local_courses'));
         $courseedit = html_writer::link($url, $icon, $options);
         $deleteurl = 'javascript:void(0)';
         $deleteicon = '<i class="icon fa fa-trash fa-fw"></i>';
         $array = array('title'=>get_string('delete'),
                  'alt'=>get_string('delete'),
                  'onclick'=>"(function(e){ require('local_courses/courses').deleteuser({ action:'delete_user', 
                              userid:".$userenrol->id.", id:".$dataobj->courseid."}) })(event)");
          $delete = html_writer::link($deleteurl, $deleteicon, $array);
          $enrolmethod[] = $courseedit.$delete.$this->render(new local_courses\output\courseevidenceview($dataobj->courseid, 
                           $enroluser->userid, 'userview'));
       }
        }
        $userinfo[] = implode('<br />', $enroll);

       $userinfo[] = implode(' <br>', $enrolmethod);

       if ($cert_plugin_exists && $certificate_added) {
          if(!empty($enroluser->timecompleted)){
            $icon = '<i class="icon fa fa-download" aria-hidden="true"></i>';
            // Mallikarjun added to download default certificate.
            $certcode = $DB->get_field('tool_certificate_issues', 'code', array('moduleid'=>$dataobj->courseid, 
                                       'userid'=>$enroluser->userid, 'moduletype'=>'course'));
            $array = array('code' =>$certcode);
            $url = new moodle_url('/admin/tool/certificate/view.php', $array);
            $options = array('title'=>get_string('download_certificate', 'local_courses'), 'target'=>'_blank');
            $userinfo[] = html_writer::link($url, $icon, $options);
          } else {
            // $icon = '<i class="icon fa fa-download" aria-hidden="true"></i>';
            $url = 'javascript: void(0)';
            $userinfo[] = html_writer::tag($url, get_string('notassigned', 'local_classroom'));
          }
        }

        $userslist[] = $userinfo;
      }

      $return = array(
          "recordsFiltered" => $enrolleduserscount,
          "data" => $userslist,
      );
    } else {
      $return = array(
          "recordsFiltered" => 0,
          "data" => array(),
      );
    }
    return $return;
  }
    public function get_userdashboard_courses($tab, $filter = false) {
        $systemcontext = context_system::instance();

        $options = array('targetID' => 'dashboard_courses', 'perPage' => 6, 'cardClass' => 'col-md-6 col-12', 'viewType' => 'card');
        $options['methodName']='local_courses_userdashboard_content_paginated';
        $options['templateName']='local_courses/userdashboard_paginated';
        $options['filter'] = $tab;
        $options = json_encode($options);
        $filterdata = json_encode(array());
        $dataoptions = json_encode(array('contextid' => $systemcontext->id));
        $context = [
                'targetID' => 'dashboard_courses',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];
        if ($filter) {
            return  $context;
        } else {
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }
    /**
     * Render the courseevidenceview
     * @param  courseevidenceview $widget
     * @return bool|string
     * @throws moodle_exception
     */
    protected function render_courseevidenceview(\local_courses\output\courseevidenceview $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_courses/courseevidence', $data);
    }
    /**
     * Render the selfcompletion
     * @param  selfcompletion $widget
     * @return bool|string
     * @throws moodle_exception
     */
    protected function render_selfcompletion(\local_courses\output\selfcompletion $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_courses/selfcompletion', $data);
    }
}
