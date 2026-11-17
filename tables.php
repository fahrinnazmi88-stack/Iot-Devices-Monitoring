<?php
// Table configuration for the generic CRUD module
$TABLES = [
 'device' => ['title'=>'IoT Devices', 'pk'=>'device_id', 'fields'=>[
   'device_code'  =>['label'=>'Device Code','type'=>'text','req'=>1],
   'device_name'  =>['label'=>'Device Name','type'=>'text','req'=>1],
   'device_type'  =>['label'=>'Type','type'=>'text','req'=>1],
   'mac_address'  =>['label'=>'MAC Address','type'=>'text','req'=>1],
   'location_id'  =>['label'=>'Location','type'=>'fk','table'=>'location','id'=>'location_id','show'=>'location_name','req'=>1],
   'status'       =>['label'=>'Status','type'=>'select','options'=>['active','inactive','maintenance'],'req'=>1],
   'installed_date'=>['label'=>'Installed Date','type'=>'date','req'=>1],
   'firmware_version'=>['label'=>'Firmware','type'=>'text'],
 ]],
 'sensor' => ['title'=>'Sensors', 'pk'=>'sensor_id', 'fields'=>[
   'device_id'    =>['label'=>'Device','type'=>'fk','table'=>'device','id'=>'device_id','show'=>'device_name','req'=>1],
   'sensor_name'  =>['label'=>'Sensor Name','type'=>'text','req'=>1],
   'sensor_type'  =>['label'=>'Type','type'=>'text','req'=>1],
   'unit'         =>['label'=>'Unit','type'=>'text','req'=>1],
   'min_threshold'=>['label'=>'Min Threshold','type'=>'number','req'=>1],
   'max_threshold'=>['label'=>'Max Threshold','type'=>'number','req'=>1],
   'status'       =>['label'=>'Status','type'=>'select','options'=>['online','offline','faulty'],'req'=>1],
   'installed_date'=>['label'=>'Installed Date','type'=>'date','req'=>1],
 ]],
 'sensor_reading' => ['title'=>'Sensor Readings', 'pk'=>'reading_id', 'fields'=>[
   'sensor_id'    =>['label'=>'Sensor','type'=>'fk','table'=>'sensor','id'=>'sensor_id','show'=>'sensor_name','req'=>1],
   'reading_value'=>['label'=>'Reading Value','type'=>'number','req'=>1],
   'reading_time' =>['label'=>'Reading Time','type'=>'datetime','req'=>1],
   'quality'      =>['label'=>'Quality','type'=>'select','options'=>['good','fair','poor'],'req'=>1],
   'remarks'      =>['label'=>'Remarks','type'=>'text'],
 ]],
 'alert' => ['title'=>'Alerts', 'pk'=>'alert_id', 'fields'=>[
   'sensor_id'    =>['label'=>'Sensor','type'=>'fk','table'=>'sensor','id'=>'sensor_id','show'=>'sensor_name','req'=>1],
   'alert_type'   =>['label'=>'Alert Type','type'=>'select','options'=>['high','low','offline'],'req'=>1],
   'alert_message'=>['label'=>'Message','type'=>'text','req'=>1],
   'alert_status' =>['label'=>'Status','type'=>'select','options'=>['open','acknowledged','resolved'],'req'=>1],
   'resolved_by'  =>['label'=>'Resolved By','type'=>'fk','table'=>'user','id'=>'user_id','show'=>'full_name'],
 ]],
 'location' => ['title'=>'Locations', 'pk'=>'location_id', 'fields'=>[
   'location_name'=>['label'=>'Location Name','type'=>'text','req'=>1],
   'building'     =>['label'=>'Building','type'=>'text','req'=>1],
   'floor_level'  =>['label'=>'Floor','type'=>'text'],
   'room_no'      =>['label'=>'Room No.','type'=>'text','req'=>1],
 ]],
 'user_device' => ['title'=>'User-Device Assignments', 'pk'=>'assignment_id', 'fields'=>[
   'user_id'      =>['label'=>'User','type'=>'fk','table'=>'user','id'=>'user_id','show'=>'full_name','req'=>1],
   'device_id'    =>['label'=>'Device','type'=>'fk','table'=>'device','id'=>'device_id','show'=>'device_name','req'=>1],
   'assigned_date'=>['label'=>'Assigned Date','type'=>'date','req'=>1],
   'responsibility'=>['label'=>'Responsibility','type'=>'text','req'=>1],
   'status'       =>['label'=>'Status','type'=>'select','options'=>['active','ended'],'req'=>1],
 ]],
];