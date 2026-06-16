<?php
// Errors
$lang['CalagopusPackage.!error.meta[nest_uuid].empty'] = 'Please select a nest.';
$lang['CalagopusPackage.!error.meta[egg_uuid].empty'] = 'Please select an egg.';
$lang['CalagopusPackage.!error.meta[deploy].valid'] = 'You must select either a node or one or more locations for deployment.';
$lang['CalagopusPackage.!error.meta[memory].format'] = 'The memory amount must be a non-negative integer.';
$lang['CalagopusPackage.!error.meta[swap].format'] = 'The swap amount must be -1 (unlimited) or a non-negative integer.';
$lang['CalagopusPackage.!error.meta[disk].format'] = 'The disk amount must be a non-negative integer.';
$lang['CalagopusPackage.!error.meta[cpu].format'] = 'The CPU limit must be a non-negative integer.';
$lang['CalagopusPackage.!error.meta[memory_overhead].format'] = 'The memory overhead must be a non-negative integer.';
$lang['CalagopusPackage.!error.meta[io_weight].format'] = 'The IO weight must be an integer between 10 and 1000.';
$lang['CalagopusPackage.!error.meta[allocations_limit].format'] = 'The allocation limit must be a non-negative integer.';
$lang['CalagopusPackage.!error.meta[database_limit].format'] = 'The database limit must be a non-negative integer.';
$lang['CalagopusPackage.!error.meta[backup_limit].format'] = 'The backup limit must be a non-negative integer.';
$lang['CalagopusPackage.!error.meta[schedule_limit].format'] = 'The schedule limit must be a non-negative integer.';
$lang['CalagopusPackage.!error.meta[pinned_cpus].format'] = 'Pinned CPUs must be a comma-separated list of integers (e.g. 0,1,2).';
$lang['CalagopusPackage.!error.meta[docker_image].length'] = 'The docker image must be at most 255 characters.';


// Package fields
$lang['CalagopusPackage.package_fields.nest_uuid'] = 'Nest';
$lang['CalagopusPackage.package_fields.tooltip.nest_uuid'] = 'The nest containing the egg to use for created servers.';

$lang['CalagopusPackage.package_fields.egg_uuid'] = 'Egg';
$lang['CalagopusPackage.package_fields.tooltip.egg_uuid'] = 'The egg to use for created servers.';

$lang['CalagopusPackage.package_fields.node_uuid'] = 'Node';
$lang['CalagopusPackage.package_fields.tooltip.node_uuid'] = 'Select a specific node, or leave as Auto to deploy by location.';

$lang['CalagopusPackage.package_fields.location_uuids'] = 'Location(s)';
$lang['CalagopusPackage.package_fields.tooltip.location_uuids'] = 'Used for automatic deployment when no specific node is selected.';

$lang['CalagopusPackage.package_fields.memory'] = 'Memory (MiB)';
$lang['CalagopusPackage.package_fields.tooltip.memory'] = 'The amount of memory to assign to created servers.';

$lang['CalagopusPackage.package_fields.swap'] = 'Swap (MiB)';
$lang['CalagopusPackage.package_fields.tooltip.swap'] = 'The amount of swap to assign. Set to -1 for unlimited, 0 to disable.';

$lang['CalagopusPackage.package_fields.disk'] = 'Disk (MiB)';
$lang['CalagopusPackage.package_fields.tooltip.disk'] = 'The amount of disk space to assign to created servers.';

$lang['CalagopusPackage.package_fields.cpu'] = 'CPU Limit (%)';
$lang['CalagopusPackage.package_fields.tooltip.cpu'] = 'The CPU limit to assign. 100 = 1 thread. Set to 0 for unlimited.';

$lang['CalagopusPackage.package_fields.memory_overhead'] = 'Memory Overhead (MiB)';
$lang['CalagopusPackage.package_fields.tooltip.memory_overhead'] = 'Hidden memory added to the container.';

$lang['CalagopusPackage.package_fields.io_weight'] = 'IO Weight';
$lang['CalagopusPackage.package_fields.tooltip.io_weight'] = 'Block IO weight (10-1000). Leave blank for default.';

$lang['CalagopusPackage.package_fields.allocations_limit'] = 'Allocation Limit';
$lang['CalagopusPackage.package_fields.tooltip.allocations_limit'] = 'The number of allocations a server may have.';

$lang['CalagopusPackage.package_fields.database_limit'] = 'Database Limit';
$lang['CalagopusPackage.package_fields.tooltip.database_limit'] = 'The number of databases a server may have.';

$lang['CalagopusPackage.package_fields.backup_limit'] = 'Backup Limit';
$lang['CalagopusPackage.package_fields.tooltip.backup_limit'] = 'The number of backups a server may have.';

$lang['CalagopusPackage.package_fields.schedule_limit'] = 'Schedule Limit';
$lang['CalagopusPackage.package_fields.tooltip.schedule_limit'] = 'The number of schedules a server may have.';

$lang['CalagopusPackage.package_fields.custom_feature_limits'] = 'Custom Feature Limits';
$lang['CalagopusPackage.package_fields.tooltip.custom_feature_limits'] = 'Extension-added feature limits. Format: key:value,key:value (e.g. plugins:5,worlds:3).';

$lang['CalagopusPackage.package_fields.docker_image'] = 'Docker Image (optional)';
$lang['CalagopusPackage.package_fields.tooltip.docker_image'] = 'Override the egg default docker image. Leave blank for the egg default.';

$lang['CalagopusPackage.package_fields.startup_command'] = 'Startup Command (optional)';
$lang['CalagopusPackage.package_fields.tooltip.startup_command'] = 'Override the egg default startup command. Leave blank for the egg default.';

$lang['CalagopusPackage.package_fields.server_name_prefix'] = 'Server Name Prefix';
$lang['CalagopusPackage.package_fields.tooltip.server_name_prefix'] = 'Prefix for auto-generated server names (e.g. "MC-"). Used when the client leaves the server name blank.';

$lang['CalagopusPackage.package_fields.pinned_cpus'] = 'Pinned CPUs';
$lang['CalagopusPackage.package_fields.tooltip.pinned_cpus'] = 'Comma-separated CPU core IDs (e.g. 0,1,2). Leave blank for no pinning.';

$lang['CalagopusPackage.package_fields.backup_configuration_uuid'] = 'Backup Configuration UUID (optional)';
$lang['CalagopusPackage.package_fields.tooltip.backup_configuration_uuid'] = 'Optional backup configuration to assign to created servers.';

$lang['CalagopusPackage.package_fields.skip_installer'] = 'Skip Egg Install Script';
$lang['CalagopusPackage.package_fields.tooltip.skip_installer'] = 'Skip the egg installation script, if one is attached.';

$lang['CalagopusPackage.package_fields.start_on_completion'] = 'Start on Completion';
$lang['CalagopusPackage.package_fields.tooltip.start_on_completion'] = 'Start the server automatically after installation.';

$lang['CalagopusPackage.package_fields.hugepages_passthrough'] = 'Hugepages Passthrough';
$lang['CalagopusPackage.package_fields.tooltip.hugepages_passthrough'] = 'Mount /dev/hugepages into the container.';

$lang['CalagopusPackage.package_fields.kvm_passthrough'] = 'KVM Passthrough';
$lang['CalagopusPackage.package_fields.tooltip.kvm_passthrough'] = 'Allow access to /dev/kvm inside the container.';

$lang['CalagopusPackage.package_fields.optional'] = '%1$s (Optional)'; // %1$s is the name of the field
$lang['CalagopusPackage.package_fields.tooltip.display'] = 'Check to allow clients to modify this value during service add/edit. Leave unchecked if you plan to use a configurable option for this field.';
