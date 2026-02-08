#
# Table structure for extending table 'pages'
#
CREATE TABLE pages (
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
    autotranslate_languages varchar(255) DEFAULT NULL,
    autotranslate_last int(11) DEFAULT '0' NOT NULL
);

#
# Table structure for extending table 'tt_content'
#
CREATE TABLE tt_content (
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
    autotranslate_languages varchar(255) DEFAULT NULL,
    autotranslate_last int(11) DEFAULT '0' NOT NULL
);

#
# Table structure for extending table 'tx_news_domain_model_news'
#
CREATE TABLE tx_news_domain_model_news (
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
    autotranslate_languages varchar(255) DEFAULT NULL,
    autotranslate_last int(11) DEFAULT '0' NOT NULL
);

#
# Table structure for extending table 'sys_file_reference'
#
CREATE TABLE sys_file_reference (
    autotranslate_last int(11) DEFAULT '0' NOT NULL
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
);

#
# Table structure for batch translation items
#
CREATE TABLE tx_autotranslate_batch_item (
    sys_language_uid int(11) DEFAULT '0' NOT NULL,
    priority varchar(255) DEFAULT '' NOT NULL,
    translate int(11) unsigned DEFAULT '0' NOT NULL,
    translated int(11) unsigned DEFAULT NULL,
    mode varchar(255) DEFAULT '' NOT NULL,
    frequency varchar(255) DEFAULT '' NOT NULL,
    error text DEFAULT '' NOT NULL,

    KEY pid (pid)
);

#
# Table structure for translation logs
#
CREATE TABLE tx_autotranslate_log (
    request_id varchar(13) DEFAULT '' NOT NULL,
    time_micro double NOT NULL DEFAULT '0',
    component varchar(255) DEFAULT '' NOT NULL,
    level tinyint(1) unsigned DEFAULT '0' NOT NULL,
    message text,
    data text,

    KEY request (request_id)
);