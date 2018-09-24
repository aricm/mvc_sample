<div ng-app="gs" ng-controller="gsController as vm" ng-cloak>
	<div growl></div>

	<div class="row">
		<div class="col-lg-3">
			<div class="panel panel-default panel-sm">
				<div class="panel-heading">
					<!-- Date picker -->
					<div class="input-group">
						<span class="input-group-btn">
							<button type="button" class="btn btn-default" ng-disabled="!vm.trip_date" ng-click="vm.prevDay()"><i class="fa fa-chevron-left fa-fw"></i></button>
						</span>
						<input type="text" class="form-control text-center datepicker" placeholder="Trip date" ng-model="vm.trip_date">
						<span class="input-group-btn">
							<button type="button" class="btn btn-default" ng-disabled="!vm.trip_date" ng-click="vm.nextDay()"><i class="fa fa-chevron-right fa-fw"></i></button>
						</span>
					</div> <!-- /end date picker -->
				</div>
				<div class="panel-body" style="text-align: center;font-weight: 700;"><span ng-show="vm.trip_date">{{vm.trip_date | date: 'EEE MMM d, yyyy'}} <span class="badge bg-success" ng-bind="vm.trip_list.length" style="display:none;"></span></span><span ng-hide="vm.trip_date">-- choose a date --</span></div>
				<div style="max-height: 500px;overflow-y: auto;">
					<ul class="list-group" style="margin-bottom: 0;">
						<li class="list-group-item list-group-item-warning clearfix" style="text-align: center;padding: 0.5em;font-size: 1.2em;" ng-hide="!vm.loading_trips"><i class="fa fa-spinner fa-pulse fa-fw"></i> Loading trips...</li>
						<li class="list-group-item clearfix" style="cursor: pointer;" ng-repeat="idx in vm.trip_list track by $index" ng-click="vm.setActiveTrip($index)" ng-class="{'active': vm.current_item.item_schedule_id == idx.item_schedule_id, 'list-group-item-success': idx.num_guides > 0}" ng-if="idx.num_reservations != 0">
						<span class="badge pull-right" title="{{idx.num_guides}} Guides Scheduled" ng-if="idx.num_guides > 0" ng-bind="idx.num_guides"></span>
							<div ng-class="{'text-success': idx.total_num_booked > 0 && idx.item_schedule_id != vm.current_item.item_schedule_id, 'text-danger': idx.total_num_booked == 0 && idx.item_schedule_id != vm.current_item.item_schedule_id }"><strong ng-bind="idx.activity_code"></strong> @ <strong ng-bind="idx.item_schedule_time || 'NO TIME' | formattime"></strong> <small><span title="Reservations"><strong>R</strong>:{{idx.num_reservations}}</span> <span tile="People booked"><strong>P</strong>:{{idx.total_num_booked}}</span> <span title="Max limit"><strong>L</strong>:{{idx.item_schedule_limit}}</span></small></div>
							<div><small ng-bind="idx.activity_description"></small></div>
						</li>
					</ul>
				</div>
			</div> <!-- END Panel -->

		</div>

		<div class="col-lg-9">

			<ul class="nav nav-tabs" ng-if="vm.current_item.item_schedule_id">
			    <li class="active"><a data-toggle="tab" href="#guideScheduling" data-toggle="tab">Scheduling</a></li>
			    <li><a href="#guideHistory" ng-click="vm.getGuideHistory(vm.current_item.item_schedule_id)" data-toggle="tab">History <span class="badge" ng-bind="vm.guide_history.length || 0"></span></a></li>
			</ul>

			<div class="tab-content">
				<div class="tab-pane active" id="guideScheduling" style="padding-top: 20px">
					<div class="panel panel-default" ng-if="vm.current_item.item_schedule_id">
						<div class="panel-heading clearfix">
							<span class="label label-primary">{{vm.current_item.activity_code}}</span> {{vm.current_item.activity_description}} @ <mark> {{vm.current_item.item_schedule_time || 'NO TIME' | formattime}} </mark> // {{vm.current_item.total_num_booked}} guests in {{vm.reservation_list.length}} reservations
							<div class="pull-right" ng-show="vm.loading_reservations"><i class="fa fa-spinner fa-pulse fa-fw"></i> Loading reservations...</div>
						</div>
						<table class="table table-condensed table-hover">
							<tr>
								<th>Rsv #</th>
		                        <th>Day</th>
								<th>Name</th>
								<th>Client</th>
								<th>Ppl</th>
								<th>Guide Requests</th>
								<th width="1%">Notes</th>
								<!-- <th>Link</th> -->
							</tr>
							<tbody>
								<tr ng-repeat="idx in vm.reservation_list track by $index">
									<td><a href="<?php echo site_url();?>/reservations/edit/{{idx.reservation_id}}" target="_blank">{{idx.reservation_id}}</a></td>
		                            <td ng-bind="idx.reservation_item_schedule_item_day"></td>
									<td><a href="<?php echo site_url();?>/guests/add_edit/{{idx.user_id}}" target="_blank">{{idx.user_last_name}}, {{idx.user_first_name}}</a></td>
									<td ng-bind="idx.reservation_client_name"></td>
									<td ng-bind="idx.reservation_num_booked"></td>
									<td>
										<div ng-repeat="req in idx.guide_requests"><small>{{req.user_employee_code}}: {{req.reservation_item_guide_request_created}}</small></div>
									</td>
									<td class="text-center"><a role="button" style="outline: none !important;" tabindex="{{$index}}" class="has-popover" title="Reservation Notes" data-content="{{idx.notes}}"><span class="badge light" ng-class="{'success': idx.num_notes > 0}" ng-bind="idx.num_notes">0</span></a></td>
									<!-- <td>{{idx.reservation_id}}_</td> -->
								</tr>
							</tbody>
						</table>
						<div class="panel-footer"><span ng-show="vm.current_item.resources.length > 0"><span class="label label-warning">RESOURCES</span>
							<ul class="inline-list">
								<li ng-repeat="item in vm.current_item.resources track by $index"><small>{{item.activity_resource_name || 'No resources'}}</small></li>
							</ul>
							</span>
							<span ng-show="vm.current_item.resources.length == 0"><i class="fa fa-exclamation-triangle" style="color: #d43f3a; fa-fw"></i> No resources associated with this activity.</span>
						</div>
					</div>



					<!-- Guides -->
					<div class="panel panel-default" ng-show="vm.current_item.item_schedule_id">
						<div class="panel-heading clearfix" ng-show="vm.current_item.activity_group_id == 4">
							<button class="btn btn-sm btn-info pull-right" ng-show="vm.current_item.activity_group_id == 4" data-toggle="modal" data-target="#boatAssignmentModal" ng-click="vm.getBoatAssignment(vm.current_item.item_schedule_id)">{{vm.current_item.num_boats}} boat<span ng-if="!vm.current_item.num_boats == 1">s</span> assigned</button>
						</div>
						<form name="theForm">
							<table class="table table-striped table-hover">
								<tr>
									<th>Action</th>
									<th width="20%">Guide</th>
									<th>Name</th>
									<th>Status</th>
									<th width="15%">Actions</th>
								</tr>
								<tr ng-form="subForm_{{$index}}" ng-repeat="item in vm.guide_schedule_list track by $index">
									<td>
										<select class="form-control" name="guide_schedule_action_id" ng-options="item.guide_action_id as item.guide_action_description for item in vm.action_options" ng-model="vm.guide_schedule_list[$index].guide_schedule_action_id">
										</select>
									</td>
									<td>
										<div class="input-group clearfix">
											<input type="text" class="form-control" name="guide_code" ng-model="vm.guide_schedule_list[$index].user_employee_code" readonly required>
											<span class="input-group-btn"><button type="button" class="btn btn-default" data-toggle="modal" data-target="#guideModal" ng-click="vm.showGuideList($index)"><i class="fa fa-th-list fa-fw"></i></button></span>
										</div>
										</div>
									</td>
									<td>
										<input type="text" class="form-control" name="guide_name" ng-model="vm.guide_schedule_list[$index].guide_name" disabled>
									</td>
									<td>
										<select class="form-control" name="guide_schedule_status_id" ng-options="item.guide_status_id as item.guide_status_description for item in vm.status_options" ng-model="vm.guide_schedule_list[$index].guide_schedule_status_id">
										</select>
									</td>
									<td>
										<button type-"button" class="btn btn-primary" ng-click="vm.saveGuide($index,item)" ng-disabled="subForm_{{$index}}.guide_code.$invalid" title="Save"><i class="fa fa-pencil fa-fw"></i></button>
										<button type="button" class="btn btn-default" ng-class="{'btn-success': item.guide_schedule_notified_by.length > 0}" data-toggle="modal" data-target="#notifyModal" ng-disabled="!item.guide_schedule_id" ng-click="vm.notify_guide_set($index)" title="Notify {{vm.guide_schedule_list[$index].guide_name}}"><i class="fa fa-envelope fa-fw"></i></button>
										<button type="button" class="btn btn-default" ng-click="vm.deleteGuide($index,item)" ng-disabled="!item.guide_schedule_id" title="Remove"><i class="fa fa-times fa-fw"></i></button>
									</td>
								</tr>
							</table>
						</form>
						{{formActionId.guide_schedule_action_id.$$parent.$pristine}}

						<div ng-show="vm.loading_guide_schedule"><i class="fa fa-spinner fa-pulse fa-fw"></i> Loading scheduled guides...</div>

					</div> <!-- END Guides -->
				</div> <!-- END tab-pane #guideScheduling -->

				<div class="tab-pane" id="guideHistory" style="padding-top: 20px">
					<table class="table table-striped">
						<thead>
							<th>What</th>
							<th>Guide</th>
							<th>By</th>
							<th>Note</th>
							<th>Action</th>
							<th>Status</th>
							<th>Date</th>
						</thead>
						<tbody>
							<tr ng-repeat="item in vm.guide_history track by $index">
								<td><span class="label label-default" ng-bind="item.guide_history_action | uppercase" ng-class="{'label-success':item.guide_history_action === 'confirmed','label-primary':item.guide_history_action === 'scheduled','label-danger':item.guide_history_action === 'unscheduled','label-info':item.guide_history_action === 'notified'}"></span></td>
								<td>{{item.user_first_name}} {{item.user_last_name}} [{{item.user_employee_code}}]</td>
								<td ng-bind="item.guide_history_action_by"></td>
								<td ng-bind="item.guide_history_note"></td>
								<td ng-bind="item.guide_action_description"></td>
								<td ng-bind="item.guide_status_description"></td>
								<td ng-bind="item.guide_history_created"></td>
							</tr>
						</tbody>
					</table>
				</div>


			</div> <!-- END tab-content -->
		</div> <!-- END col -->
	</div> <!-- END row -->



	<!-- Guide List Modal -->
	<div class="modal fade" tabindex="-1" role="dialog" id="guideModal">
	  <div class="modal-dialog modal-lg">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
	        <h4 class="modal-title"><span class="badge" ng-bind="vm.guide_list.length"></span> Qualified Guides <button type="button" title="Refresh" class="btn btn-xs btn-primary " ng-click="vm.getGuideList(vm.current_item.resources)"><i class="fa fa-refresh fa-fw" ng-class="{'fa-pulse': vm.loadingQualifiedGuides}" ng-disabled="vm.loadingQualifiedGuides"></i></button></h4>
	      </div>
	      <div class="modal-body">

			<div class="row">
				<div class="col-sm-12">
					{{vm.current_item.activity_description}} @ <mark> {{vm.current_item.item_schedule_time | formattime}} </mark><br />
					<small>{{vm.current_item.total_num_booked}} guests in {{vm.reservation_list.length}} reservations
					- {{vm.current_item.num_guides}} assigned guides
					<span ng-if="vm.current_item.activity_group_id == 4"> - {{vm.current_item.num_boats}} boat(s)</span></small>
				</div>
			</div>
			<div ng-if="vm.guide_list.length > 0 && !vm.loadingQualifiedGuides">
				<div class="row" >
					<div class="col-sm-12">
							<table class="table table-condensed">
								<thead>
									<tr>
										<th><small>&nbsp;</small></th>
										<th><small>Last Name</small></th>
										<th><small>First Name</small></th>
										<th><small>ID</small></th>
										<th><small>Qualifications</small></th>
										<th><small>Availability</small></th>
									</tr>
								</thead>
								<tbody>
									<tr ng-repeat="guide in vm.guide_list track by $index" ng-class="{'bg-danger': guide.scheduled.length > 0 || guide.availability.length > 0}">
										<td><button type="button" class="btn btn-xs btn-primary" ng-class="{'btn-danger': guide.scheduled.length > 0}" ng-click="vm.setGuide(guide)" data-dismiss="modal">Assign</button></td>
										<td>{{guide.user_last_name}}</td>
										<td>{{guide.user_first_name}}</td>
										<td>{{guide.user_employee_code}}</td>
										<td>{{guide.guide_actions}}</td>
										<td>
											<div ng-repeat="item in guide.scheduled"><small>{{item.item_schedule_time}}: {{item.activity_description}}</small></div>
											<div ng-repeat="item in guide.availability"><small>{{item.guide_availability_note}}</small></div>
										</td>
									</tr>
								</tbody>
							</table>
							<!-- 								<table class="table table-condensed" style="margin-bottom: 0;" ng-if="guide.schedule.length > 0" >
								<tr><td style="padding: 2px;" width="20%" class="text-center" ng-repeat="date in vm.guide_list_date_range track by $index"><small style="font-size: 9px;">{{date == vm.trip_date ? 'Selected Date' : date}}</small></td></tr>
								<tr>
									<td style="padding: 1px;" class="text-center" ng-repeat="date in vm.guide_list_date_range track by $index">
										<div ng-repeat="time in guide.schedule track by $index">
											<div class="bg-danger" ng-if="time.item_schedule_day == date" title="{{time.activity_description}}"><small>{{time.item_schedule_time | formattime}}</small></div>
											<div class="bg-danger" ng-if="time.guide_availability_date == date" title="{{time.guide_availability_note}}"><small>Unavailable</small></div>
										</div>
									</td>
								</tr>
							</table>
							<div class="text-center bg-success" ng-if="guide.schedule.length == 0"><small>Available</small></div> -->
						</div>
					</div>
				</div>
				<div class="alert alert-warning" ng-if="vm.loadingQualifiedGuides"><i class="fa fa-spinner fa-pulse"></i> Loading qualified guides...</div>
				<div class="alert alert-warning" ng-if="vm.guide_list.length == 0 && !vm.loadingQualifiedGuides">No guides are qualified for this trip/resource(s)</div>

	      </div><!-- /.modal-body -->
	    </div><!-- /.modal-content -->
	  </div><!-- /.modal-dialog -->
	</div><!-- /.modal -->

	<!-- Boast Assignment Modal -->
	<div class="modal fade" tabindex="-1" role="dialog" id="boatAssignmentModal">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close" ng-click="vm.boat_assignment = null"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title"><i class="fa fa-ship fa-fw"></i>{{vm.current_item.item_schedule_time | formattime}} - {{vm.current_item.activity_description}}</h4>
				</div>
				<div class="modal-body">

				<div class="row" ng-if="vm.boat_assignment.length > 0">
					<div class="col-sm-4" ng-repeat="boat in vm.boat_assignment track by boat.boat_id">
						<div class="panel panel-default" ng-style="vm.dropBoat(boat.num_people)">
							<div class="panel-heading"><span class="pull-right">{{boat.num_people}} / {{vm.current_item.activity_max_in_boat}} <span class="label label-success" style="font-size: 100%;" ng-class="{'label-danger': boat.num_people >= vm.current_item.activity_max_in_boat}">{{vm.current_item.activity_max_in_boat - boat.num_people}}</span></span> <strong>Boat {{$index+1}}</strong></div>
							<table class="table table-condensed table-bordered" style="font-size: 12px;" ng-if="boat.assigned.length > 0">
								<tr class="bg-warning">
									<td width="1%"></td>
									<td width="1%">Rsvno</td>
									<td>Name</td>
									<td width="2%">Ppl</td>
								</tr>
								<tr ng-repeat="rsv in boat.assigned track by rsv.boat_assignment_id" ng-class="{'bg-info': rsv.boat_assignment_reservation_item_id == vm.cur.assigned_rsv.reservation_item_id}">
									<td style="color: #999;">{{$index + 1}}</td>
									<td>{{rsv.reservation_id}}</td>
									<td>{{rsv.user_last_name}}, {{rsv.user_first_name}}</td>
									<td rowspan="0">{{rsv.boat_assignment_num_people}}</td>
								</tr>
							</table>
							<div class="panel-body text-center" ng-if="boat.assigned.length === 0">
								<p>There are no assignments yet.</p>
							 </div>
						</div>
					</div>
				</div><!-- /row - boats -->

				<div class="row" ng-if="vm.boat_assignment.length === 0">
					<div class="col-sm-12">
						<div class="alert alert-warning">
							<p>There are no boats assigned to this trip yet.</p>
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-sm-12">
						<h4>Trip Notes <span ng-show="vm.busy_notes"><i class="fa fa-spinner fa-pulse"></i> Fetching trip notes...</span></h4>
						<table class="table table-condensed" style="margin-bottom: 10px;" ng-repeat="note in vm.trip_note_list track by $index" ng-if="vm.trip_note_list.length > 0">
							<tbody>
								<tr>
									<td><small>{{note.trip_note}}</small></td>
								</tr>
							</tbody>
							<tfoot>
								<tr>
									<td class="text-muted"><small><em>Added by <strong>{{note.user_first_name}} {{note.user_last_name}}</strong> on <strong>{{note.trip_note_created_formatted}}</strong></em></small></td>
								</tr>
							</tfoot>
						</table>
						<div class="alert alert-warning" ng-if="vm.trip_note_list.length == 0">
							<p>There are no notes for this trip</p>
						</div>
					</div>
				</div>

				</div><!-- /.modal-body -->
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal" ng-click="vm.boat_assignment = null">Close</button>
				</div>
			</div><!-- /.modal-content -->
		</div><!-- /.modal-dialog -->
	</div><!-- /.modal -->

	<!-- Notify Guide Modal -->
	<div class="modal fade" tabindex="-1" role="dialog" id="notifyModal">
	  <div class="modal-dialog">
	    <form method="post" name="nofityGuideForm">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
	        <h4 class="modal-title"><i class="fa fa-envelope fa-fw"></i> Notify {{vm.notify_guide.name}}</h4>
	      </div>
	      <div class="modal-body">
	      	<div class="row">
	      		<div class="col-sm-12">
	      			<div class="alert alert-warning" ng-if="vm.notify_guide.notified_by.length > 0">
	      				<p>{{vm.notify_guide.name}} was last sent a notification on {{vm.notify_guide.notified_on | dateToISO | date:'fullDate'}} @ {{vm.notify_guide.notified_on | dateToISO | date:'shortTime'}}</p>
	      			</div>
	      			<table class="table table-condensed table-borderless">
	      				<tbody>
	      					<tr>
	      						<td class="text-right" width="1%"><strong>Activity</strong>:</td>
	      						<td>{{vm.current_item.activity_description}}</td>
	      					</tr>
	      					<tr>
	      						<td class="text-right"><strong>Date</strong>:</td>
	      						<td>{{vm.current_item.item_schedule_day | dateToISO | date:'fullDate'}}</td>
	      					</tr>
	      					<tr>
	      						<td class="text-right"><strong>Time</strong>:</td>
	      						<td>{{vm.current_item.item_schedule_time | formattime}}</td>
	      					</tr>
	      					<tr>
	      						<td class="text-right"><strong>Action</strong>:</td>
	      						<td>{{vm.notify_guide.action}}</td>
	      					</tr>
	      					<tr>
	      						<td class="text-right"><strong>Status</strong>:</td>
	      						<td>{{vm.notify_guide.status}}</td>
	      					</tr>
	      				</tbody>
	      			</table>
	      		</div>
	      	</div>
	      	<div class="row">
		        <div class="col-lg-12">
		        	<div class="form-group">
		        		<textarea class="form-control" name="gs_notify_guide" ng-model="vm.notify_note" placeholder="Type here to send a custom note..."></textarea>
		        	</div>
		        </div>
	      	</div><!-- /.row -->
	      </div><!-- /.modal-body -->
	      <div class="modal-footer">
	      	<button type="submit" class="btn btn-primary" ng-click="vm.notifyGuide()"><i class="fa fa-envelope fa-fw"></i> Send</button>
	      	<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times fa-fw"></i> close</button>
	      </div>
	    </div><!-- /.modal-content -->
	    </form
	  </div><!-- /.modal-dialog -->
	</div><!-- /.modal -->

</div>
<?php
?>

<script>
	var guide_schedule_action      = "<?php echo $this->config->item('guide_schedule_action');?>";
	var guide_schedule_confirmed   = "<?php echo $this->config->item('guide_schedule_confirmed');?>";
	var guide_schedule_unconfirmed = "<?php echo $this->config->item('guide_schedule_unconfirmed');?>";
	var user_id                    = "<?php echo $this->data['user'][0]['user_id'];?>";
</script>
