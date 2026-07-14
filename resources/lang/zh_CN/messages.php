<?php

return [

    // Action
    'action' => [
        'label' => '导入',
    ],

    // Modal
    'modal' => [
        'heading' => '导入数据',
    ],

    // Steps
    'step' => [
        'upload' => '上传文件',
        'mapping' => '列映射',
        'preview' => '数据预览',
        'results' => '导入结果',
    ],

    // Upload
    'upload' => [
        'label' => '上传文件 (CSV, XLSX, ODS)',
        'dropzone' => '点击上传或拖拽文件到此',
        'supported' => '支持格式：CSV, XLSX, ODS',
        'download_template' => '下载 CSV 模板',
    ],

    // Mapping
    'mapping' => [
        'title' => '列映射',
        'file_header' => '文件列名',
        'field_name' => '系统字段',
        'placeholder' => '-- 请选择字段 --',
        'auto_mapped' => '自动匹配',
        'unmatched' => '未匹配',
        'ignored' => '跳过此列',
    ],

    // Preview
    'preview' => [
        'title' => '数据预览',
        'total_rows' => '共 :count 条',
        'valid_rows' => '校验通过 :count 条',
        'duplicate_behavior' => '重复数据处理',
        'no_data' => '没有可预览的数据',
    ],

    // Results
    'results' => [
        'title' => '导入结果',
        'summary' => '成功 :imported 条，跳过 :skipped 条，失败 :failed 条',
        'imported' => '导入成功：:count 条',
        'skipped' => '跳过重复：:count 条',
        'failed' => '导入失败：:count 条',
        'download_errors' => '下载错误报告',
    ],

    // Wizard
    'wizard' => [
        'next' => '下一步',
        'previous' => '上一步',
        'start_import' => '开始导入',
        'close' => '关闭',
        'importing' => '正在导入...',
    ],

    // Duplicate behavior
    'duplicate_behavior' => [
        'skip' => '跳过重复',
        'update' => '更新已有记录',
        'error' => '标记为错误',
    ],

    // Import status
    'import_status' => [
        'pending' => '待处理',
        'previewing' => '预览中',
        'processing' => '处理中',
        'completed' => '已完成',
        'failed' => '导入失败',
        'partial' => '部分成功',
    ],

    // Error messages
    'errors' => [
        'no_file' => '没有上传文件。',
        'invalid_file' => '不支持的文件格式。支持：CSV, XLSX, ODS。',
        'empty_file' => '文件为空，请检查后重新上传。',
        'no_data' => '文件中没有数据，请检查后重新上传。',
        'file_too_large' => '文件过大，最大允许 :size MB。',
        'upload_incomplete' => '文件未通过验证，无法执行下一步。',
        'import_failed' => '导入失败。',
        'no_errors' => '没有错误可下载。',
        'model_not_set' => '未设置模型类。',
    ],

    // Import Log
    'import_log' => [
        'file_name' => '文件名',
        'model_class' => '模型',
        'total_rows' => '总行数',
        'imported' => '导入成功',
        'skipped' => '已跳过',
        'failed' => '导入失败',
        'status' => '状态',
        'created_at' => '导入时间',
        'details' => '导入详情',
        'delete' => '删除',
        'delete_selected' => '批量删除',
        'delete_confirm' => '确定要删除这条导入记录吗？',
        'user' => '执行人',
        'date_from' => '开始日期',
        'date_to' => '结束日期',
        'config' => '导入配置',
        'duplicate_behavior' => '重复处理',
        'chunk_size' => '批次大小',
        'unique_by' => '去重字段',
        'column_mapping' => '列映射',
        'field' => '字段',
        'value' => '值',
        'error' => '错误信息',
        'started_at' => '开始时间',
        'finished_at' => '结束时间',
        'duration' => '耗时',
        're_import' => '重新导入',
        're_import_desc' => '使用相同的配置导入新文件。',
        'actions' => '操作',
        're_import_hint' => '请前往对应的资源页面，使用「导入」按钮上传新文件。以上是上一次导入使用的配置。',
        're_import_hint_upload' => '请上传与原文件列结构一致的 CSV 或 Excel 文件。列映射、重复处理策略等配置将自动沿用上一次导入的设置。',
    ],

    // Stats
    'stats' => [
        'total_imports' => '导入总次数',
        'records_imported' => '已导入记录数',
        'failed_imports' => '失败次数',
        'failure_rate' => '失败率',
        'skipped_records' => '跳过记录数',
    ],


    // Resources
    'resources' => [
        'import_logs' => '导入日志',
        'import_log' => '导入日志',
    ],

    // Import method
    'import_method' => '处理方式',
    'import_method_sync' => '同步',
    'import_method_async' => '队列',

    // License
    'license' => [
        'page_title' => '授权设置',
        'navigation_label' => '授权管理',
        'navigation_group' => '设置',
        'status_heading' => '授权状态',
        'activation_heading' => '激活授权',
        'features_heading' => '功能列表',
        'status_unknown' => '未知',
        'license_key_label' => '授权密钥',
        'license_key_hint' => '输入您的 Pro 授权密钥以解锁全部功能。',
        'license_key_placeholder' => '请输入授权密钥...',
        'activate_button' => '激活',
        'deactivate_button' => '解除绑定',
        'features_free' => '免费功能',
        'features_pro' => 'Pro 功能（需要授权）',
        'feature_basic_import' => '基础导入',
        'feature_csv_support' => 'CSV 支持',
        'feature_excel_support' => 'Excel 支持',
        'feature_auto_mapping' => '自动映射',
        'feature_advanced_mapping' => '高级映射',
        'feature_queue' => '队列导入',
        'feature_import_log' => '导入历史',
        'feature_merge_split' => '合并与拆分列',
        'feature_pipeline' => '数据管道',
        'license_key' => '授权密钥',
    ],
];
