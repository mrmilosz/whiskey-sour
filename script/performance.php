<?php
$config = json_decode(file_get_contents('server.json'), true);

$valid_property_names = array('pcpu', 'pmem', 'etime', 'time', 'comm');

$output = array();

# Get the names of the requested properties from the query string
$client_format_string = isset($_GET['o']) ? $_GET['o'] : '';

# Sanitize the properties and split them into an array string
$requested_property_names = array_unique(array_filter(preg_split('/\s*,\s*/', $client_format_string), function($property_name) use ($valid_property_names) {
	return in_array($property_name, $valid_property_names);
}));

# Add the comm property to the properties
$ps_property_names = array_unique(array_merge($requested_property_names, array('comm')));

# Build the ps format string
$ps_format_string = implode(',', $ps_property_names);

# Build a shell command that gets information about the quake server process
$shell_command = "ps -p `cat ${config['pidfile_path']}` -o $ps_format_string";

# Capture output (exec gives the last line)
$command_output = exec($shell_command);

# Check if the output is the actual values, not the headings
if ($command_output[0] !== '%') {
	# Interpret output as list of floats
	$property_values = array_map(function($token) {
		return trim($token);
	}, preg_split('/\s+/', $command_output));

	# Make an associative array of the property names and values
	$ps_property_values = array_combine($ps_property_names, $property_values);

	# Make sure the monitored process is actually quake!
	if (preg_match($config['quake_executable_regex'], $ps_property_values['comm'])) {
		# Add the requested values to the output by key
		foreach ($requested_property_names as $property_name) {
			$output[$property_name] = $ps_property_values[$property_name];
		}
	}
}

header('Content-Type: application/json');
echo json_encode((object) $output);
