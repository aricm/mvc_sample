// Bind datepicker on focus -- needed due to dynamically created angular inputs
$('body').on('focus',".datepicker", function(){
    $(this).datepicker({dateFormat: 'yy-mm-dd', changeYear: true, changeMonth: true});
});
$('body').popover({
	selector: '.has-popover',
	placement: 'left',
	trigger: 'hover focus',
	html: true
});

// Helper
// Get index of array by object prop and value
function objectIndexOf(myArray, searchTerm, property) {
    for (var i = 0, len = myArray.length; i < len; i++) {
        if (myArray[i][property] === searchTerm) return i;
    }
    return -1;
}


// AJAX calls to /controller/function_name can be found in controller.php
var app = angular.module('gs',['ngAnimate','angular.filter','angular.formattime','angular.dateToISO','angular-growl'])
    .config(['growlProvider', function (growlProvider) {
        growlProvider.globalTimeToLive(3000);
        growlProvider.globalDisableCountDown(true);
        growlProvider.globalDisableCloseButton(false);
        growlProvider.globalPosition('top-center');
}])
	.controller('gsController',function($scope,$http,growl){
	vm = this;

	var config = {}; // for growl

	// Fetch list of existing scheduled trips for given day
	vm.getTripList = function(trip_date) {
		vm.loading_trips = true;
		$http({
		    method: 'get',
		    url: site_url+'/controller/get_trip_list',
		    params: { date: trip_date }
		}).then(function successCallback(response){
		    if(response.data.success){
		    	// Populate trip_list[] array with the response
		        vm.trip_list = response.data.items;
		    } else {
		        alert(response.data.error);
		    }
		    vm.loading_trips = null;
		},function errorCallback(response){
		    alert('There was an error fetching the trip list.');
		    vm.loading_trips = null;
		});
	};

	// Get all reservations associated with currently selected trip
	function getReservationList(id) {
		vm.loading_reservations = true;
		$http({
		    method: 'get',
		    url: site_url+'/controller/get_reservation_list',
		    // id is the item_scheduling_id
		    params: { id: id }
		}).then(function successCallback(response){
		    if(response.data.success){
		    	// Populate array with the response
		        vm.reservation_list = response.data.items;
		    } else {
		        alert(response.data.error);
		    }
			vm.loading_reservations = null;
		},function errorCallback(response){
		    alert('There was an error fetching the reservations list.');
			vm.loading_reservations = null;
		});
	}

	// Get all assigned guides to currently selected trip and day
	vm.getAssignedGuides = function(item_id,day_num) {
		vm.loading_guide_schedule = true;
		day_num = typeof day_num !== 'undefined' ? day_num : 1;
		vm.current_item.active_day = day_num;
		$http({
		    method: 'get',
		    url: site_url+'/controller/get_assigned_guides',
		    // id is the item_scheduling_id
		    params: { id: item_id, day: day_num }
		}).then(function successCallback(response){
		    if(response.data.success){
		    	// Populate array with the response
		        vm.guide_schedule_list = response.data.items;

		        // Default empty array item always exists
		        pushEmptyGuide();
		    } else {
		        alert(response.data.error);
		    }
			vm.loading_guide_schedule = null;
		},function errorCallback(response){
		    alert('There was an error fetching the guide schedule list.');
			vm.loading_guide_schedule = null;
		});
	};

	// Get all resources associated with trip
	vm.getActivityResources = function(item_id) {
		$http({
		    method: 'get',
		    url: site_url+'/controller/get_activity_resources',
		    // id is the activity_id
		    params: { id: item_id }
		}).then(function successCallback(response){
		    if(response.data.success){
		    	// Add array to vm.currrent_item
		         vm.current_item.resources = response.data.items;
		         vm.getGuideList(vm.current_item.resources);
		    } else {
		        alert(response.data.error);
		    }
			vm.loading_guide_schedule = null;
		},function errorCallback(response){
		    alert('There was an error fetching the guide schedule list.');
		});
	};


	// Assign or update a guide to current trip/day
	vm.saveGuide = function(idx,obj) {
		var day_num = typeof vm.current_item.active_day === 'undefined' ? 1 : vm.current_item.active_day;
		$http({
		    method:'post',
		    url: site_url+'/controller/save_guide',
		    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
		    data:{
		        item:obj,
		        day_num: day_num
		    }
		}).then(function successCallback(response) {
		    if(response.data.success){
		        if(response.data.result >= 1) {
		        	// An insert was performed, add the insertID to the object so we don't have re-fetch the guide schedule list
		        	vm.guide_schedule_list[idx].guide_schedule_id = response.data.result;
		        	// Update the trip with new number of guides
		        	vm.current_item.num_guides++;
		        	// Add an empty row to the guide schedule list
		        	pushEmptyGuide();
		        }
		        vm.getGuideList(vm.current_item.resources);
	        	growl.success("<strong>Saved successfully</strong>",config);
		    } else {
		        alert(response.data.error);
		    }
		},function errorCallback(response) {
			alert('There was an error fetching some list.');
		});
	};


	// Remove assigned guide from this trip
	vm.deleteGuide = function(idx,obj) {
		var remove = confirm('Are you sure you want to remove ' + vm.guide_schedule_list[idx].guide_name + '?');
		if(remove === true){
		    $http({
		    	method:'post',
		    	url: site_url+'/controller/delete_guide',
		    	headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
		        data:{
		            item:obj
		        }
		    }).then(function successCallback(response){
		        if(response.data.success){
		            vm.guide_schedule_list.splice(idx, 1);
		            vm.current_item.num_guides--;
		            vm.getGuideList(vm.current_item.resources);
		        	growl.success("<strong>Guide successfully unscheduled</strong>",config);
		        }else{
		            // alert(response.data.error);
		        	growl.error(response.data.error,config);
		        }
		    },function errorCallback(response){
		    	alert('An error occurred.');
		    });
		}
	};

	// Fetch all available qualified guides for the modal
	// @idx = index of the ng-repeat
	// @resources [array] - activity resources

	vm.getGuideList = function(resources) {
		vm.loadingQualifiedGuides = true;
		$http({
		    method: 'get',
		    url: site_url+'/controller/get_guide_list',
		    params: { resources: JSON.stringify(resources), date: vm.trip_date }
		}).then(function successCallback(response){
		    if(response.data.success){
		    	// Populate guide_list array with the response
		        vm.guide_list = response.data.items;
		        // vm.guide_list_date_range = response.data.date_range;
		    } else {
		        alert(response.data.error);
		    }
		    vm.loadingQualifiedGuides = false;
		},function errorCallback(response){
		    vm.loadingQualifiedGuides = false;
		    alert('There was an error fetching the guide list.');
		});
	};

	vm.showGuideList = function(idx,resources) {
		vm.guide_schedule_idx = idx;
	};

	// Create an empty row for a new guide in the schedule list
	function pushEmptyGuide() {
		vm.guide_schedule_list.push({
			guide_schedule_item_schedule_id: vm.current_item.item_schedule_id,
			guide_schedule_action_id: guide_schedule_action.toString(),
			guide_schedule_status_id: guide_schedule_unconfirmed.toString(),
		});
	}

	// Assign current_item to the clicked trip
	vm.setActiveTrip = function(idx) {
		vm.current_item = vm.trip_list[idx];
		vm.current_item.active_day = 1;
		// Select the "Scheduling" tab
		$('.nav a[href=#guideScheduling]').tab('show');
		getReservationList(vm.current_item.item_schedule_id);
		vm.getAssignedGuides(vm.current_item.item_schedule_id);
		vm.getActivityResources(vm.current_item.activity_id);
		vm.getGuideHistory(vm.current_item.item_schedule_id);
	};

	// Assign guide properties to the given line item after clicked from the modal
	vm.setGuide = function(guide) {
		var confirmed = false;
		if(guide.scheduled.length > 0) {
			confirmed = confirm(guide.user_first_name + ' ' + guide.user_last_name + " is currently scheduled on this date for " + guide.scheduled.length + " activities.\n\n Are you sure you want to schedule them?");
		} else {
			confirmed = true;
		}
		if(confirmed) {
			var idx = vm.guide_schedule_idx;
			vm.guide_schedule_list[idx].user_employee_code = guide.user_employee_code;
			vm.guide_schedule_list[idx].guide_name = guide.user_first_name + ' ' + guide.user_last_name;
			// vm.guide_schedule_list[idx].guide_schedule_user_id = guide.guide_qualification_user_id;
			vm.guide_schedule_list[idx].guide_schedule_user_id = guide.user_id;
		}
	};

	vm.notify_guide = {}; // Notification modal (idx of guide_schedule_list, plus other information needed for display)
	vm.notify_guide_set = function(idx) {
		vm.notify_guide.idx = idx;
		vm.notify_guide.action = vm.action_options[objectIndexOf(vm.action_options,vm.guide_schedule_list[idx].guide_schedule_action_id,'guide_action_id')].guide_action_description;
		vm.notify_guide.status = vm.status_options[objectIndexOf(vm.status_options,vm.guide_schedule_list[idx].guide_schedule_status_id,'guide_status_id')].guide_status_description;
		vm.notify_guide.name = vm.guide_schedule_list[idx].guide_name;
		vm.notify_guide.notified_on = vm.guide_schedule_list[idx].guide_schedule_notified_on;
		vm.notify_guide.notified_by = vm.guide_schedule_list[idx].guide_schedule_notified_by;
		vm.notify_note = '';
	};

	// Send an email to the guide scheduled for this trip
	vm.notifyGuide = function() {
		$http({
		    method:'post',
		    url: site_url+'/controller/notify_guide',
		    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
		    data:{
		        // guide_schedule_id: vm.guide_schedule_list[vm.notify_guide.idx].guide_schedule_id,
		        item: vm.guide_schedule_list[vm.notify_guide.idx],
		        note: vm.notify_note
		    }
		}).then(function successCallback(response) {
		    if(response.data.success){
		    	$('#notifyModal').modal('toggle');
	        	growl.success("<strong>"+vm.guide_schedule_list[vm.notify_guide.idx].guide_name+" was successfully notified</strong>",config);
	        	vm.guide_schedule_list[vm.notify_guide.idx].guide_schedule_notified_on = response.data.datetime;
	        	vm.guide_schedule_list[vm.notify_guide.idx].guide_schedule_notified_by = user_id;
	        	vm.notify_guide = {};
		    } else {
		        alert(response.data.error);
		    }
		},function errorCallback(response) {
			alert('There was an error fetching some list.');
		});
	};

	// Options for select menu
	function getStatusOptions() {
		$http({
		    method: 'get',
		    url: site_url+'/controller/get_status_options',
		    params: { }
		}).then(function successCallback(response){
		    if(response.data.success){
		    	// Populate trip_list[] array with the response
		        vm.status_options = response.data.items;
		    } else {
		        alert(response.data.error);
		    }
		},function errorCallback(response){
		    alert('There was an error fetching the status options.');
		});
	}

	// Options for select menu
	function getActionOptions() {
		$http({
		    method: 'get',
		    url: site_url+'/controller/get_action_options',
		    params: { }
		}).then(function successCallback(response){
		    if(response.data.success){
		    	// Populate trip_list[] array with the response
		        vm.action_options = response.data.items;
		    } else {
		        alert(response.data.error);
		    }
		},function errorCallback(response){
		    alert('There was an error fetching the action options.');
		});
	}

	vm.nextDay = function() {
		vm.trip_date = moment(vm.trip_date).add(1,'d').format("YYYY-MM-DD");
	};
	vm.prevDay = function() {
		vm.trip_date = moment(vm.trip_date).subtract(1,'d').format("YYYY-MM-DD");
	};

	vm.getBoatAssignment = function(schedule_id) {
		vm.trip_note_list = [];
		$http({
		    method: 'get',
		    url: site_url+'/trip_assignment/get_boats',
		    params: { schedule_id: schedule_id }
		}).then(function successCallback(response){
		    if(response.data.success){
		    	// Populate trip_list[] array with the response
		        vm.boat_assignment = response.data.items;
		        vm.getTripNotes();
		    } else {
		        alert(response.data.error);
		    }
		},function errorCallback(response){
		    alert('There was an error fetching the action options.');
		});
	};

	// Retrieve notes for a specific trip
	vm.getTripNotes = function() {
		vm.busy_notes = true;
		$http({
		    method: 'get',
		    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
		    url: site_url+'/trip_assignment/get_trip_notes',
		    params: {
		        item_schedule_id: vm.current_item.item_schedule_id
		    }
		}).then(function successCallback(response) {
		    if(response.data.success){
		        vm.trip_note_list = response.data.notes;
		    } else {
		        alert(response.data.error);
		    }
			vm.busy_notes = false;
		},function errorCallback(response) {
		    alert('there was an error');
			vm.busy_notes = false;
		});
	};

	/** GUIDE HISTORY */
	vm.getGuideHistory = function(schedule_id) {
		$http({
		    method: 'get',
		    url: site_url+'/controller/get_guide_history',
		    params: { schedule_id: schedule_id }
		}).then(function successCallback(response){
		    if(response.data.success){
		    	// Populate
		        vm.guide_history = response.data.items;
		    } else {
		        alert(response.data.error);
		    }
		},function errorCallback(response){
		    alert('There was an error fetching the history items.');
		});
	};


	$scope.$watch('vm.trip_date', function() {
		// console.log(vm.trip_date);
		vm.getTripList(vm.trip_date);
		vm.current_item = {};
	});


	///////////////////////
	// Initialization
	//////////////////////
	getStatusOptions();
	getActionOptions();
	vm.trip_date         = moment().format("YYYY-MM-DD");
	vm.site_url          = site_url;
	vm.current_item      = {};
	vm.choose_notes_open = false;

});



angular.module('angular.formattime',[])
	.filter('formattime',
    function () {
        return function (time,seconds) {
            if (!time) { return ''; }

            // Check correct time format and split into components
            time = time.toString ().match (/^([01]\d|2[0-3])(:)([0-5]\d)(:[0-5]\d)?$/) || [time];

            if (time.length > 1) { // If time format correct
              time = time.slice (1);  // Remove full string match value
              if(!seconds) { // By default we don't want seconds
              	time.splice(3,1); // Remove seconds
              }
              time[5] = +time[0] < 12 ? ' AM' : ' PM'; // Set AM/PM
              time[0] = +time[0] % 12 || 12; // Adjust hours
            }
            return time.join (''); // return adjusted time or original string
        };
    });

angular.module('angular.dateToISO',[])
	.filter('dateToISO',
	function () {
	    return function (badTime) {
	    	if (!badTime) { return ''; }
	    	var goodTime = badTime.replace(/(.+) (.+)/, "$1T$2Z");
	    	return goodTime;
	    };
	});

