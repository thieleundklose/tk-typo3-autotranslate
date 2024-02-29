#
# Table structure for extending table 'pages'
#
CREATE TABLE pages (
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
    autotranslate_languages varchar(255) DEFAULT NULL,
    autotranslate_last int(11) DEFAULT '0' NOT NULL,
);

#
# Table structure for extending table 'tt_content'
#
CREATE TABLE tt_content (
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
    autotranslate_languages varchar(255) DEFAULT NULL,
    autotranslate_last int(11) DEFAULT '0' NOT NULL,
);

#
# Table structure for extending table 'tx_news_domain_model_news'
#
CREATE TABLE tx_news_domain_model_news (
    -- Bugfix:
    -- Sql statement includes uid to prevent db compare bug if news extension is installed after autotranslate.
    uid int(11) NOT NULL auto_increment,
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
    autotranslate_languages varchar(255) DEFAULT NULL,
    autotranslate_last int(11) DEFAULT '0' NOT NULL,
    PRIMARY KEY (uid),
);

#
# Table structure for extending table 'sys_file_reference'
#
CREATE TABLE sys_file_reference (
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
    autotranslate_last int(11) DEFAULT '0' NOT NULL,
);

CREATE TABLE tx_autotranslate_batch_items (
    priority int(11) DEFAULT '0' NOT NULL, 

);