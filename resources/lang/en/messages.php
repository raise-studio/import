<?php

return [

    // Action
    'action' => [
        'label' => 'Import',
    ],

    // Modal
    'modal' => [
        'heading' => 'Import Data',
    ],

    // Steps
    'step' => [
        'upload' => 'Upload File',
        'mapping' => 'Column Mapping',
        'preview' => 'Preview',
        'results' => 'Results',
    ],

    // Upload
    'upload' => [
        'label' => 'Upload your file (CSV, XLSX, ODS)',
        'dropzone' => 'Click to upload or drag and drop',
        'supported' => 'Supported formats: CSV, XLSX, ODS',
        'download_template' => 'Download CSV Template',
    ],

    // Mapping
    'mapping' => [
        'title' => 'Column Mapping',
        'file_header' => 'File Column',
        'field_name' => 'System Field',
        'placeholder' => '-- Select Field --',
        'auto_mapped' => 'Auto-detected',
        'unmatched' => 'Unmatched',
        'ignored' => 'Skip Column',
    ],

    // Preview
    'preview' => [
        'title' => 'Data Preview',
        'total_rows' => 'Total rows: :count',
        'valid_rows' => ':count rows validated',
        'duplicate_behavior' => 'Duplicate Handling',
        'no_data' => 'No data to preview',
    ],

    // Results
    'results' => [
        'title' => 'Import Results',
        'summary' => ':imported imported, :skipped skipped, :failed failed',
        'imported' => 'Successfully imported: :count',
        'skipped' => 'Skipped (duplicates): :count',
        'failed' => 'Failed: :count',
        'download_errors' => 'Download Error Report',
    ],

    // Wizard
    'wizard' => [
        'next' => 'Next',
        'previous' => 'Previous',
        'start_import' => 'Start Import',
        'close' => 'Close',
        'importing' => 'Importing...',
    ],

    // Duplicate behavior
    'duplicate_behavior' => [
        'skip' => 'Skip duplicates',
        'update' => 'Update existing records',
        'error' => 'Mark as error',
    ],

    // Import status
    'import_status' => [
        'pending' => 'Pending',
        'previewing' => 'Previewing',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'partial' => 'Partial',
    ],

    // Error messages
    'errors' => [
        'no_file' => 'No file was uploaded.',
        'invalid_file' => 'Invalid file format. Supported: CSV, XLSX, ODS.',
        'empty_file' => 'The file is empty. Please check and re-upload.',
        'no_data' => 'The file contains no data. Please check and re-upload.',
        'file_too_large' => 'File too large. Maximum allowed size is :size MB.',
        'upload_incomplete' => 'File validation failed. Cannot proceed to the next step.',
        'import_failed' => 'Import failed.',
        'no_errors' => 'No errors to download.',
        'model_not_set' => 'Model class is not configured.',
    ],

    // Import Log
    'import_log' => [
        'file_name' => 'File Name',
        'model_class' => 'Model',
        'total_rows' => 'Total Rows',
        'imported' => 'Imported',
        'skipped' => 'Skipped',
        'failed' => 'Failed',
        'status' => 'Status',
        'created_at' => 'Imported At',
        'details' => 'Import Details',
        'delete' => 'Delete',
        'delete_selected' => 'Delete Selected',
        'delete_confirm' => 'Are you sure you want to delete this import log?',
        'user' => 'User',
        'date_from' => 'From Date',
        'date_to' => 'To Date',
        'config' => 'Import Configuration',
        'duplicate_behavior' => 'Duplicate Handling',
        'chunk_size' => 'Batch Size',
        'unique_by' => 'Unique Columns',
        'column_mapping' => 'Column Mapping',
        'field' => 'Field',
        'value' => 'Value',
        'error' => 'Error',
        'started_at' => 'Started At',
        'finished_at' => 'Finished At',
        'duration' => 'Duration',
        're_import' => 'Re-import',
        're_import_desc' => 'Use the same configuration to import a new file.',
        'actions' => 'Actions',
        're_import_hint' => 'Go to the corresponding Resource table and use the Import button to upload a new file. The settings above were used for the previous import.',
        're_import_hint_upload' => 'Upload a new file with the same column structure. Column mapping, duplicate handling, and other settings from the original import will be reused automatically.',
    ],

    // Stats
    'stats' => [
        'total_imports' => 'Total Imports',
        'records_imported' => 'Records Imported',
        'failed_imports' => 'Failed Imports',
        'failure_rate' => 'failure rate',
        'skipped_records' => 'Skipped Records',
    ],

    // Resources
    'resources' => [
        'import_logs' => 'Import Logs',
        'import_log' => 'Import Log',
    ],

    // Import method
    'import_method' => 'Method',
    'import_method_sync' => 'Sync',
    'import_method_async' => 'Queue',

    // License
    'license' => [
        'page_title' => 'License Settings',
        'navigation_label' => 'License',
        'navigation_group' => 'Settings',
        'status_heading' => 'License Status',
        'activation_heading' => 'Activate License',
        'features_heading' => 'Features',
        'status_unknown' => 'Unknown',
        'license_key_label' => 'License Key',
        'license_key_hint' => 'Enter your Pro license key to activate all features.',
        'license_key_placeholder' => 'Enter your license key...',
        'activate_button' => 'Activate',
        'deactivate_button' => 'Deactivate',
        'features_free' => 'Free Features',
        'features_pro' => 'Pro Features (requires license)',
        'feature_basic_import' => 'Basic Import',
        'feature_csv_support' => 'CSV Support',
        'feature_excel_support' => 'Excel Support',
        'feature_auto_mapping' => 'Auto Mapping',
        'feature_advanced_mapping' => 'Advanced Mapping',
        'feature_queue' => 'Queue Import',
        'feature_import_log' => 'Import History',
        'feature_merge_split' => 'Merge & Split Columns',
        'feature_pipeline' => 'Data Pipeline',
        'license_key' => 'License Key',
    ],
];
