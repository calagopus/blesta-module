<?php
$lang['Calagopus.name'] = 'Calagopus';
$lang['Calagopus.description'] = 'Provisions and manages game servers via the Calagopus panel.';
$lang['Calagopus.module_row'] = 'Server';
$lang['Calagopus.module_row_plural'] = 'Servers';
$lang['Calagopus.module_group'] = 'Server Group';

$lang['Calagopus.back_to_manage'] = 'Back';
$lang['Calagopus.please_select.node'] = '-- Auto (use locations) --';


// Errors
$lang['Calagopus.!error.server_name.empty'] = 'You must enter a server label.';
$lang['Calagopus.!error.host_name.valid'] = 'The Panel URL appears to be invalid.';
$lang['Calagopus.!error.api_key.empty'] = 'You must enter an API key.';
$lang['Calagopus.!error.api_key.valid'] = 'Unable to connect with the given Panel URL and API key.';
$lang['Calagopus.!error.module_row.missing'] = 'An internal error occurred. The module row is unavailable.';
$lang['Calagopus.!error.server.missing'] = 'The server could not be found on the panel.';
$lang['Calagopus.!error.allocation.available'] = 'There are no available allocations on the selected node.';
$lang['Calagopus.!error.deploy.location'] = 'No node or location is configured for this package.';
$lang['Calagopus.!error.user.conflict'] = 'A panel user with this username already exists and was not linked automatically. Verify that it belongs to this client before linking it manually.';
$lang['Calagopus.!error.import.module'] = 'The Pterodactyl module is not installed, so there is nothing to import.';
$lang['Calagopus.!error.import.row_map'] = 'Select a Calagopus server to import at least one Pterodactyl server into.';


// Add module row
$lang['Calagopus.add_row.box_title'] = 'Add Calagopus Server';
$lang['Calagopus.add_row.basic_title'] = 'Basic Settings';
$lang['Calagopus.add_row.add_btn'] = 'Add Server';


// Edit module row
$lang['Calagopus.edit_row.box_title'] = 'Edit Calagopus Server';
$lang['Calagopus.edit_row.basic_title'] = 'Basic Settings';
$lang['Calagopus.edit_row.add_btn'] = 'Edit Server';


// Module row meta data
$lang['Calagopus.row_meta.server_name'] = 'Server Label';
$lang['Calagopus.row_meta.host_name'] = 'Panel URL';
$lang['Calagopus.row_meta.api_key'] = 'API Key';
$lang['Calagopus.row_meta.default_language'] = 'Default User Language';


// Module row tooltips
$lang['Calagopus.!tooltip.host_name'] = 'Enter the URL of your Calagopus panel, e.g. https://panel.example.com. HTTPS is used if no protocol is given.';
$lang['Calagopus.!tooltip.api_key'] = 'Enter the admin API key generated in your Calagopus panel.';
$lang['Calagopus.!tooltip.default_language'] = 'The default language assigned to newly created panel users (e.g. "en").';


// Module management
$lang['Calagopus.order_options.first'] = 'First Non-full Server';

$lang['Calagopus.add_module_row'] = 'Add Server';
$lang['Calagopus.add_module_group'] = 'Add Server Group';
$lang['Calagopus.manage.module_rows_title'] = 'Servers';
$lang['Calagopus.manage.module_groups_title'] = 'Server Groups';
$lang['Calagopus.manage.tab_rows'] = 'Servers';
$lang['Calagopus.manage.tab_groups'] = 'Server Groups';
$lang['Calagopus.manage.module_rows_heading.name'] = 'Server Label';
$lang['Calagopus.manage.module_rows_heading.host_name'] = 'Panel URL';
$lang['Calagopus.manage.module_rows_heading.options'] = 'Options';
$lang['Calagopus.manage.module_groups_heading.name'] = 'Group Name';
$lang['Calagopus.manage.module_groups_heading.servers'] = 'Server Count';
$lang['Calagopus.manage.module_groups_heading.options'] = 'Options';
$lang['Calagopus.manage.module_rows.edit'] = 'Edit';
$lang['Calagopus.manage.module_groups.edit'] = 'Edit';
$lang['Calagopus.manage.module_rows.delete'] = 'Delete';
$lang['Calagopus.manage.module_groups.delete'] = 'Delete';
$lang['Calagopus.manage.module_rows.confirm_delete'] = 'Are you sure you want to delete this server?';
$lang['Calagopus.manage.module_groups.confirm_delete'] = 'Are you sure you want to delete this server group?';
$lang['Calagopus.manage.module_rows_no_results'] = 'There are no servers.';
$lang['Calagopus.manage.module_groups_no_results'] = 'There are no server groups.';


// Pterodactyl import
$lang['Calagopus.manage.import_title'] = 'Import from Pterodactyl';
$lang['Calagopus.manage.import_description'] = 'The Pterodactyl module currently has %1$s package(s) and %2$s service(s) assigned to it. Choose which Calagopus server each Pterodactyl server should be imported into, then click Import. Packages are reassigned to this module (nests, eggs, and locations are matched by name between the two panels) and services are relinked to their servers on the Calagopus panel. Make sure your servers have already been migrated to the Calagopus panel before importing.';
$lang['Calagopus.manage.import_heading.name'] = 'Pterodactyl Server';
$lang['Calagopus.manage.import_heading.host_name'] = 'Panel URL';
$lang['Calagopus.manage.import_heading.target'] = 'Import Into';
$lang['Calagopus.manage.import_skip_row'] = '-- Do not import --';
$lang['Calagopus.manage.import_btn'] = 'Import';
$lang['Calagopus.manage.import_confirm'] = 'Are you sure you want to import from Pterodactyl? Packages and services will be reassigned to the Calagopus module. This cannot be undone.';
$lang['Calagopus.manage.import_no_rows'] = 'The Pterodactyl module is installed, but it has no servers to import from.';
$lang['Calagopus.manage.import_success'] = 'Import complete. %1$s package(s) and %2$s service(s) were migrated to the Calagopus module.';
$lang['Calagopus.manage.import_failed'] = 'The import request failed. Please try again.';

$lang['Calagopus.import.note_package_unmapped'] = 'Package #%1$s was skipped because its Pterodactyl server is not mapped to a Calagopus server.';
$lang['Calagopus.import.note_package_unresolved'] = 'Package #%1$s was skipped because its %2$s could not be matched by name on the Calagopus panel.';
$lang['Calagopus.import.note_package_group'] = 'Package #%1$s used a Pterodactyl server group and was assigned directly to the selected Calagopus server instead.';
$lang['Calagopus.import.note_service_unmatched'] = 'Service #%1$s could not be matched to a server on the Calagopus panel. Its fields were migrated, but the server UUID is blank.';
$lang['Calagopus.import.entity_nest'] = 'nest';
$lang['Calagopus.import.entity_egg'] = 'egg';
$lang['Calagopus.import.entity_location'] = 'location';


// Service Info
$lang['Calagopus.service_info.server_name'] = 'Server Name';
$lang['Calagopus.service_info.server_uuid'] = 'Server UUID';
$lang['Calagopus.service_info.status'] = 'Status';
$lang['Calagopus.service_info.address'] = 'Address';
$lang['Calagopus.service_info.memory'] = 'Memory';
$lang['Calagopus.service_info.disk'] = 'Disk';
$lang['Calagopus.service_info.node'] = 'Node';
$lang['Calagopus.service_info.panel'] = 'Panel';
$lang['Calagopus.service_info.open_panel'] = 'Go to Server Panel';
$lang['Calagopus.service_info.unavailable'] = 'Server information is not available.';
