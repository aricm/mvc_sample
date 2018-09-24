<?php
class Guide_scheduling_model extends CI_Model{
    function __construct(){
        parent::__construct();
    }

    /*
    **  JUST FOR DEMONSTRATION HERE, I AM USING BOTH MANUAL QUERIES AND CODEIGNITER'S QUERY BUILDER
    */


    // Get all scheduled trips
    public function get_trip_list($date) {
        $the_query = "SELECT items_schedule.item_schedule_id, item_schedule_day, item_schedule_time, item_schedule_limit
                , activity_code, activity_description, activity_duration_days, activity_id, activity_group_id, activity_max_in_boat
                , reservation_item_adults, reservation_item_youth, COUNT(reservations.reservation_status) AS num_reservations
                FROM items_schedule
                JOIN activities ON item_schedule_item_id = activity_id
                LEFT JOIN reservation_item_schedule_items ON (items_schedule.item_schedule_id = reservation_item_schedule_items.item_schedule_id AND reservation_item_schedule_item_day)
                LEFT JOIN reservation_items ON reservation_item_schedule_items.reservation_item_id = reservation_items.reservation_item_id
                LEFT JOIN reservations ON (reservation_items.reservation_id = reservations.reservation_id AND reservations.reservation_status = 1)
                WHERE item_schedule_section_id = 1
                AND item_schedule_day = '$date'
                -- AND item_schedule_time IS NOT NULL
                -- AND item_schedule_section_id = 1
                GROUP BY item_schedule_id
                ORDER BY activity_description, item_schedule_time";

        $query = $this->db->query($the_query);
        // echo $this->db->last_query();
        return $query->result_array();
    }

    // Get reservations for a trip
    public function get_reservation_list($id) {

        $the_query = "SELECT reservation_items.reservation_item_id, reservation_items.reservation_id, SUM(reservation_item_to_price_breakdown_item_qty) AS reservation_num_booked, user_id, user_first_name, user_last_name, reservation_item_schedule_items.item_schedule_id, reservation_item_schedule_item_day, reservations.reservation_client_name
            FROM reservation_items

            JOIN reservation_item_schedule_items ON(
                reservation_item_schedule_items.reservation_item_id = reservation_items.reservation_item_id
            )

            JOIN reservations ON reservation_items.reservation_id = reservations.reservation_id
            JOIN reservation_item_to_price_breakdown_item ON reservation_item_to_price_breakdown_item.reservation_item_id = reservation_items.reservation_item_id
            JOIN users ON reservations.reservation_user_id = user_id
            WHERE reservation_item_schedule_items.item_schedule_id = $id
            AND reservation_status = 1
            AND reservation_item_wait_list = 0
            GROUP BY reservation_item_to_price_breakdown_item.reservation_item_id
            ORDER BY user_last_name";

        $query = $this->db->query($the_query);
        return $query->result_array();
    }

    // Get guides assigned to trip
    public function get_assigned_guides($id,$day = 1) {
        $the_query = "SELECT guide_schedule.*, user_id, user_employee_code, CONCAT(user_first_name, ' ', user_last_name) AS guide_name
            FROM guide_schedule
            JOIN users ON guide_schedule_user_id = user_id
            WHERE guide_schedule_item_schedule_id = $id
            AND guide_schedule_day_num = $day";

        $query = $this->db->query($the_query);
        return $query->result_array();
    }

    // Get available guides
    public function get_guide_list($resources) {
        $this->db->select("GROUP_CONCAT(guide_action_action SEPARATOR ',') as guide_action_codes, GROUP_CONCAT(guide_action_description SEPARATOR ',') as guide_actions, user_id, user_first_name, user_last_name, user_employee_code")
                 ->from('guide_qualifications')
                 ->join('users','(user_id = guide_qualification_user_id AND user_active_guide = 1')
                 ->join('guide_actions','guide_qualification_guide_action_id = guide_action_id','LEFT')
                 ->order_by('user_last_name ASC')
                 ->group_by('guide_qualification_user_id');

        if(is_array($resources) && !empty($resources)) {
            $this->db->where_in('guide_qualification_activity_resource_id',$resources);
        }

        $result = $this->db->get()->result_array();
        // echo $this->db->last_query();
        return $result;
    }

    // Get an individual guide's schedule for a date range (guide scheduling page)
    public function get_guide_schedule($from,$to,$guide_id,$resources = '') {
        $the_query = "SELECT item_schedule_day, item_schedule_time, activity_description, activity_code, user_id, user_first_name, user_last_name, user_employee_code, user_email, guide_action_action, guide_action_description, guide_status_code, guide_status_description
            FROM guide_schedule
            JOIN items_schedule ON guide_schedule_item_schedule_id = item_schedule_id
            JOIN guide_actions ON guide_schedule_action_id = guide_action_id
            JOIN guide_status ON guide_schedule_status_id = guide_status_id
            JOIN activities ON item_schedule_item_id = activity_id
            JOIN users ON guide_schedule_user_id = user_id
            WHERE guide_schedule_user_id = $guide_id
            AND item_schedule_day BETWEEN '$from' AND '$to'
            ORDER BY item_schedule_time";

            $query = $this->db->query($the_query);
            return $query->result_array();
    }

    // Get an individual guide's work schedule in a date range (guide work schedule - report)
    // @todo Could be combined with above query with conditionals
    public function get_guide_work_schedule($from,$to,$guide_id,$resources = '') {
        $the_query = "SELECT item_schedule_day, item_schedule_time, activity_description, activity_code, activity_duration_hours, user_first_name, user_last_name, user_employee_code, guide_action_action, guide_action_description, guide_status_code, guide_status_description
            FROM guide_schedule
            JOIN items_schedule ON guide_schedule_item_schedule_id = item_schedule_id
            JOIN guide_actions ON guide_schedule_action_id = guide_action_id
            JOIN guide_status ON guide_schedule_status_id = guide_status_id
            JOIN activities ON item_schedule_item_id = activity_id
            JOIN activity_daily_resource_time ON activity_id = activity_daily_resource_time_activity_id
            JOIN users ON guide_schedule_user_id = user_id
            WHERE guide_schedule_user_id = $guide_id
            AND item_schedule_day BETWEEN '$from' AND '$to'";
            if(!empty($resources)) {
                $the_query .= " AND activity_daily_resource_time_item_id IN (" . implode(',', $resources) . ")";
            }
            $the_query .= " ORDER BY item_schedule_time";

            $query = $this->db->query($the_query);
            return $query->result_array();
    }


    // Get activity resources for specific trip
    public function get_activity_resources($activity_id) {
        $the_query = "SELECT activity_resource_id, activity_resource_code, activity_resource_name
            FROM activity_daily_resource_time
            LEFT JOIN activity_resources ON activity_daily_resource_time_item_id = activity_resource_id
            WHERE activity_daily_resource_time_activity_id = $activity_id
            AND activity_daily_resource_time_type = 1";

        $query = $this->db->query($the_query);
        return $query->result_array();
    }

    // Get guide requests per reservation item id
    public function get_guide_requests($rsv_item_id) {
        $the_query = "SELECT user_employee_code,user_first_name, user_last_name, reservation_item_guide_request_created
            FROM reservation_items
            JOIN reservation_item_guide_requests ON reservation_items.reservation_item_id = reservation_item_guide_requests.reservation_item_id
            JOIN users ON reservation_item_guide_request_user_id = user_id
            WHERE reservation_items.reservation_item_id = $rsv_item_id
            ORDER BY reservation_item_guide_request_created";

        $query = $this->db->query($the_query);
        return $query->result_array();
    }

    public function get_note_categories() {

        $this->db->select('note_category_id');
        $this->db->from('note_categories');
        $this->db->where('note_category_show_on_guide_schedule', 1);

        $query = $this->db->get();
        return $query->result_array();
    }


    // Get assigned boat count for trip
    public function get_num_boats($schedule_id) {
        $the_query = "SELECT COUNT(*) as num_boats
            FROM boats
            WHERE boat_item_schedule_id = $schedule_id
            GROUP BY boat_item_schedule_id";

        $query = $this->db->query($the_query);
        return $query->result_array();
    }

    // Get info for a scheduled guide for notification email
    public function get_assigned_guide($id) {
        $the_query = "SELECT guide_schedule.*, user_first_name, user_last_name, user_email, item_schedule_day, item_schedule_time, activity_code, activity_description, guide_action_description, guide_status_description
            FROM guide_schedule
            JOIN users ON guide_schedule_user_id = user_id
            JOIN items_schedule ON guide_schedule_item_schedule_id = item_schedule_id
            JOIN activities ON item_schedule_item_id = activity_id
            JOIN guide_actions ON guide_schedule_action_id = guide_action_id
            JOIN guide_status ON guide_schedule_status_id = guide_status_id
            WHERE guide_schedule_id = $id";

        $query = $this->db->query($the_query);
        return $query->result_array();
    }

    // Schedule for individual guide in date range
    // public function get_work_schedule($from,$to) {
    //     $the_query = "SELECT user_employee_code, user_first_name, user_last_name, guide_schedule_item_schedule_id, item_schedule_day, item_schedule_time, activity_code, activity_description
    //         FROM users
    //         LEFT JOIN guide_schedule ON user_id = guide_schedule_user_id
    //         LEFT JOIN items_schedule ON guide_schedule_item_schedule_id = item_schedule_id
    //         LEFT JOIN activities ON item_schedule_item_id = activity_id
    //         WHERE user_is_guide = 1
    //         AND user_active_guide = 1
    //         ORDER BY user_last_name, user_first_name";

    //         $query = $this->db->query($the_query);
    //         return $query->result_array();
    // }

    public function get_active_guides($guides = array()) {

        $this->db->select('user_id, user_last_name, user_first_name, user_employee_code');
        $this->db->from('users');
        $this->db->where('user_is_guide', 1);
        $this->db->where('user_active_guide', 1);
        $this->db->order_by('user_last_name','ASC');
        $this->db->order_by('user_first_name','ASC');

        if(!empty($guides)) {
            $this->db->where('user_id IN (' . implode(', ',$guides) . ')');
        }

        $query = $this->db->get();
        return $query->result_array();
    }

    public function get_history($schedule_id) {
        $this->db->select("guide_history.*, users.user_last_name, users.user_first_name, users.user_employee_code, CONCAT(IFNULL(gsop.user_first_name,''), ' ', IFNULL(gsop.user_last_name,'')) as guide_history_action_by, guide_status_description,guide_action_description");
        $this->db->from('guide_history');
        $this->db->join('users','user_id = guide_history_guide_user_id','LEFT');
        $this->db->join('users as gsop','gsop.user_id = guide_history_user_id','LEFT');
        $this->db->join('guide_status','guide_status_id = guide_history_status_id','LEFT');
        $this->db->join('guide_actions','guide_action_id = guide_history_action_id','LEFT');
        $this->db->where('guide_history_item_schedule_id', $schedule_id);
        $this->db->order_by('guide_history_created','DESC');

        $query = $this->db->get();
        return $query->result_array();
    }
}
