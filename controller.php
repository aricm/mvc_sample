<?php
/**
|   GUIDES HAVE A ROLE_ID OF 3
|
|   @todo
**/

defined('BASEPATH') OR exit('No direct script access allowed');

class Guide_scheduling extends ACE_Controller{
    public function __construct(){
        parent::__construct();

        $this->load->model('scheduling_model');

        $this->data['active_section'][] = 'guides';
    }

    public function index(){
        /** LOAD NECESSARY JAVASCRIPT FILES */
        $this->data['scripts'][] = 'angular.js';
        $this->data['scripts'][] = 'angular-animate.min.js';
        $this->data['scripts'][] = 'angular-filter.js';
        $this->data['scripts'][] = 'angular-growl.min.js';
        $this->data['scripts'][] = 'script.js';

        // Highlight link in navigation
        $this->data['active_section'][] = 'guides';
        $this->data['active_section'][] = 'guide_scheduling';

        // Page title
        $this->data['title'] = 'Guide Scheduling';

        // Load view with necessary data -- everything else will populate via AJAX calls
        $this->template->write_view('content','view',$this->data);
        $this->template->render();
    }




    /** API/AJAX Calls From script.js**/

    // Get options for HTML select menus
    public function get_status_options() {
        $items = $this->Common_model->get('guide_status',array('guide_status_active'=>1),array(),'*',array('guide_status_display_order'=>'ASC'));
        echo json_encode(array('success'=>'Success','items'=>$items));
        exit;
    }
    public function get_action_options() {
        $items = $this->Common_model->get('guide_actions',array('guide_action_active'=>1),array(),'*',array('guide_action_display_order'=>'ASC'));
        echo json_encode(array('success'=>'Success','items'=>$items));
        exit;
    }

    // Populate booked rafting trips for the specified date
    public function get_trip_list() {
        $this->load->helper('reservations_helper');
        if($this->input->get('date')) {
            $items = $this->scheduling_model->get_trip_list($this->input->get('date'));

            if(!empty($items)) {
                foreach($items as $key => $val){
                    // Are there any guides scheduled? Use helper function
                    $items[$key]['num_guides'] = $this->getTripGuideCount($val['item_schedule_id']);
                    // Get total reservations
                    $items[$key]['total_num_booked'] = $val['reservation_item_adults'] + $val['reservation_item_youth'];
                    // Assigned boats if rafting trip
                    if($val['activity_group_id'] == 4) {
                        $num_boats = $this->scheduling_model->get_num_boats($val['item_schedule_id']);
                        $items[$key]['num_boats'] = !empty($num_boats) ? $num_boats[0]['num_boats'] : 0;
                    }
                }
            }

        } else { // no date passed
            echo json_encode(array('error'=>'Must pass a date'));
            exit;
        }

        echo json_encode(array('success'=>'Success','items'=>$items));
        exit;
    }

    // Helper function
    function getTripGuideCount($trip_id = null) {
        $result = false;
        if($trip_id) {
            // Quick call to DB
            $items = $this->Common_model->get('guide_schedule',array('guide_schedule_item_schedule_id'=>$trip_id));
            $result = count($items);
        }
        return $result;
    }

    // Fetch reservations for specific trip
    public function get_reservation_list() {
        $this->load->helper('reservations_helper');
        if($this->input->get('id')) {
            $items = $this->scheduling_model->get_reservation_list($this->input->get('id'));

            if(!empty($items)) {
                foreach($items as $key => $val){
                    // Get guide request information
                    $items[$key]['guide_requests'] = $this->scheduling_model->get_guide_requests($val['reservation_item_id']);
                    // Get total reservations
                    $items[$key]['total_num_booked'] = reservationBookedQty($val['item_schedule_id']);
                    // Get internal notes from DB
                    $notes = $this->getReservationNotes($val['reservation_id']);
                    $items[$key]['notes'] = $notes['notes'];
                    $items[$key]['num_notes'] = $notes['num_notes'];

                }
            }

        } else { // no ID passed
            echo json_encode(array('error'=>'Must pass the item scheduling id'));
            exit;
        }

        echo json_encode(array('success'=>'Success','items'=>$items));
        exit;
    }


    // Get the notes for the given reservation ID
    function getReservationNotes($rsv_id) {
        $this->load->helper('reservations_helper');

        $results = getShowableNotes($rsv_id,'guide_schedule');
        $results = makePopoverNotes($results);

        return $results;
    }

    // Get information related to this type of trip so guides with only
    // necessary credentials can be selected
    public function get_activity_resources() {
        if($this->input->get('id')) {
            $items = $this->scheduling_model->get_activity_resources($this->input->get('id'));
        } else { // no id passed
            echo json_encode(array('error'=>'Must pass an an activity id'));
            exit;
        }

        echo json_encode(array('success'=>'Success','items'=>$items));
        exit;
    }

    // Guides assigned to a rafting trip already
    public function get_assigned_guides() {
        if($this->input->get('id')) {
            $items = $this->scheduling_model->get_assigned_guides($this->input->get('id'),$this->input->get('day'));
        } else { // no id passed
            echo json_encode(array('error'=>'Must pass an item schedule id'));
            exit;
        }

        echo json_encode(array('success'=>'Success','items'=>$items));
        exit;
    }

    // Get all available guides
    public function get_guide_list() {
        // Get the resource IDs for the SQL query
        $data = json_decode($this->input->get('resources'),true);
        $date = $this->input->get('date');

        $resources = array(); // Default
        if(!empty($data)) {
            $resourceArr = array();
            foreach($data as $key => $value) {
                $resources[] = $value['activity_resource_id'];
            }
        }
        // Get the guides that meet the resource criteria
        $items = $this->scheduling_model->get_guide_list($resources);

        if(!empty($items)) {
            foreach($items as $key => $val) {
                // Get guides scheduled for any activity for that day
                // @todo move to model
                $this->db->select("DATE_FORMAT(item_schedule_time,'%l:%i%p') item_schedule_time, activity_description")
                         ->from('guide_schedule')
                         ->join('items_schedule','guide_schedule_item_schedule_id = item_schedule_id')
                         ->join('activities','item_schedule_item_id = activity_id')
                         ->where('guide_schedule_user_id',$val['user_id'])
                         ->where('item_schedule_day',$date)
                         ;
                $items[$key]['scheduled'] = $this->db->get()->result_array();

                // Get availability (guides can request days off)
                // @todo move to model
                $this->db->select('guide_availability_note')
                         ->from('guide_availability')
                         ->where('guide_availability_user_id',$val['user_id'])
                         ->where('guide_availability_date',$date)
                         ;

                $items[$key]['availability'] = $this->db->get()->result_array();

            }
        }

        echo json_encode(array('success'=>'Success','items'=>$items));
        exit;
    }

    // Insert or Update guide for this trip
    public function save_guide() {
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);

        // We only require that there is data and a userID
        if(!empty($data) && (isset($data->item->guide_schedule_user_id) && !empty($data->item->guide_schedule_user_id))) {
            $this->load->helper('guide_history_helper');

            $item_schedule_id = $data->item->guide_schedule_item_schedule_id;

            // Prep data
            $dataArray = array();

            $dataArray['guide_schedule_item_schedule_id'] = $item_schedule_id;
            $dataArray['guide_schedule_day_num']          = $data->day_num;
            $dataArray['guide_schedule_user_id']          = $data->item->guide_schedule_user_id;
            $dataArray['guide_schedule_action_id']        = $data->item->guide_schedule_action_id;
            $dataArray['guide_schedule_status_id']        = $data->item->guide_schedule_status_id;

            /** PREP INITIAL HISTORY DATA */
            $historyDetails = array();
            $historyDetails['item_schedule_id'] = $item_schedule_id;
            $historyDetails['guide_user_id']    = $data->item->guide_schedule_user_id;
            $historyDetails['action_id']        = $data->item->guide_schedule_action_id;
            $historyDetails['status_id']        = $data->item->guide_schedule_status_id;

            // If an item_schedule_id exists perform an update, perform an insert otherwise
            if(isset($data->item->guide_schedule_id) && !empty($data->item->guide_schedule_id)) {

                // Update
                $result = $this->Common_model->update('guide_schedule',$dataArray,array('guide_schedule_id'=>$data->item->guide_schedule_id));
                if($result) {
                    // History details
                    $historyDetails['guide_schedule_id'] = $data->item->guide_schedule_id;
                    $historyDetails['action']            = 'updated';
                    addGuideHistoryItem($item_schedule_id,$historyDetails);
                    echo json_encode(array('success'=>'Guide schedule updated.','result'=>0));
                } else {
                    echo json_encode(array('error'=>'An error occurred. Guide schedule could not be updated.'));
                }
            } else {
                // Insert
                $result = $this->Common_model->insert('guide_schedule',$dataArray);
                if($result) {
                    // History detail
                    $historyDetails['action']            = 'scheduled';
                    $historyDetails['guide_schedule_id'] = $result;
                    addGuideHistoryItem($item_schedule_id,$historyDetails);
                    echo json_encode(array('success'=>'Guide scheduled.','result'=>$result));
                } else {
                    echo json_encode(array('error'=>'An error occurred. Guide could not be scheduled.'));
                }
            }

        } else {
            echo json_encode(array('error'=>'No data to insert or missing user ID'));
        }
        exit;
    }

    // Unassign a scheduled guide
    public function delete_guide(){
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);

        if($data->item->guide_schedule_id){
            $this->load->helper('guide_history_helper');

            $item_schedule_id = $data->item->guide_schedule_item_schedule_id;
            $item_id          = $data->item->guide_schedule_id;

            /** PREP INITIAL HISTORY DATA */
            $historyDetails = array();
            $historyDetails['action']            = 'unscheduled';
            $historyDetails['item_schedule_id']  = $item_schedule_id;
            $historyDetails['guide_schedule_id'] = $item_id;
            $historyDetails['guide_user_id']     = $data->item->guide_schedule_user_id;
            $historyDetails['action_id']         = $data->item->guide_schedule_action_id;
            $historyDetails['status_id']         = $data->item->guide_schedule_status_id;

            $item = $this->Common_model->delete('guide_schedule',array('guide_schedule_id'=>$item_id));

            addGuideHistoryItem($item_schedule_id,$historyDetails);

            echo json_encode(array('success'=>'Success','item'=>$item));

        }else{
            echo json_encode(array('error'=>'Must pass a record id'));
        }
        exit;
    }


    // Email a scheduling notification to the guide
    public function notify_guide() {
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);

        if(isset($data->item->guide_schedule_id) && !empty($data->item->guide_schedule_id)) {

            $result = $this->scheduling_model->get_assigned_guide($data->item->guide_schedule_id);

            $details = $result[0];

            // Set template vars
            $this->emailData['title']     = 'Schedule Notification';
            $this->emailData['firstname'] = $details['user_first_name'];
            $this->emailData['lastname']  = $details['user_last_name'];
            $this->emailData['activity']  = $details['activity_description'];
            $this->emailData['date']      = $details['item_schedule_day'];
            $this->emailData['time']      = $details['item_schedule_time'];
            $this->emailData['action']    = $details['guide_action_description'];
            $this->emailData['status']    = $details['guide_status_description'];
            $this->emailData['status_id'] = $details['guide_schedule_status_id'];
            if(isset($data->note) && !empty($data->note)) {
                $this->emailData['note'] = $data->note;
            }

            $subject = 'Schedule Notification';
            $to = $details['user_email'];

            // Set layout template
            $this->template->set_template('email');

            /** Set view and send email */
            $this->template->write_view('content','emails/guide_schedule',$this->emailData);
            $message = $this->template->render(null,true);
            $this->load->helper('ace_email_helper');

            // Uncomment the 2 lines below to text without sending an actual email
            $now = date("Y-m-d H:i:s");
            $notified['guide_schedule_notified_by'] = $this->data['user'][0]['user_id'];
            $notified['guide_schedule_notified_on'] = $now;

            $this->Common_model->update('guide_schedule', $notified, array('guide_schedule_id'=>$data->item->guide_schedule_id));

            $this->load->helper('guide_history_helper');

            $item_schedule_id = $data->item->guide_schedule_item_schedule_id;
            $item_id          = $data->item->guide_schedule_id;

            /** PREP INITIAL HISTORY DATA */
            $historyDetails = array();
            $historyDetails['action']            = 'notified';
            $historyDetails['note']              = (isset($data->note) ? $data->note : '');
            $historyDetails['item_schedule_id']  = $item_schedule_id;
            $historyDetails['guide_schedule_id'] = $item_id;
            $historyDetails['guide_user_id']     = $data->item->guide_schedule_user_id;
            $historyDetails['action_id']         = $data->item->guide_schedule_action_id;
            $historyDetails['status_id']         = $data->item->guide_schedule_status_id;

            addGuideHistoryItem($item_schedule_id,$historyDetails);


            ace_send_email($subject,$message,$to);

            echo json_encode(array('success'=>'Success','datetime'=>$now));
            exit;

            if(ace_send_email($subject,$message,$to)) {
                $notified = array();
                $notified['guide_schedule_notified_by'] = $this->data['user'][0]['user_id'];
                $notified['guide_schedule_notified_on'] = date("Y-m-d H:i:s");

                if(!empty($updateTs)) {
                    $this->Common_model->update('reservations', $notified, array('guide_schedule_id'=>$data->guide_schedule_id));
                }
                    Add guide history item

                echo json_encode(array('success'=>'Success'));
            } else {
                echo json_encode(array('error'=>'An error occurred sending the email.'));
            }
        } else {
            echo json_encode(array('error'=>'You must pass a guide schedule ID'));
        }
    }

    public function get_guide_history() {
        if($this->input->get('schedule_id') && !empty($this->input->get('schedule_id'))) {

            $items = $this->scheduling_model->get_history($this->input->get('schedule_id'));

            if(!empty($items)) {
                foreach($items as &$val) {
                    $val['guide_history_created'] = date("Y-d-m @ g:ia",strtotime($val['guide_history_created']));
                }
            }

            // return items
            echo json_encode(array('success'=>true,'items'=>$items));

        } else {
            echo json_encode(array('error'=>'You must pass a schedule ID'));
        }
        exit;
    }

    public function get_guide_history_count() {
        if($this->input->get('schedule_id') && !empty($this->input->get('schedule_id'))) {

            $items = $this->Common_model->get_history('guide_history',$this->input->get('schedule_id'));
            echo json_encode(array('success'=>true,'count'=>count($items)));
        }
    }

/**
*** HELPER FUNCTIONS
**/

    function dateRange($first, $last, $step = '+1 day', $output_format = 'Y-m-d' ) {

        $dates   = array();
        $current = strtotime($first);
        $last    = strtotime($last);

        while( $current <= $last ) {
            $dates[] = date($output_format, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }

}
