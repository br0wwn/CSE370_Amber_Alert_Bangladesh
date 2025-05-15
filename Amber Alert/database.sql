CREATE DATABASE amber_alert;
USE amber_alert;

-- First create tables without foreign key dependencies
CREATE TABLE volunteer_name_group (
    group_id INT PRIMARY KEY AUTO_INCREMENT,
    area VARCHAR(100),
    group_name VARCHAR(100)
);

CREATE TABLE user (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    NID VARCHAR(20) UNIQUE,
    DOB DATE NOT NULL,
    Description TEXT,
    Area VARCHAR(100) NOT NULL,
    Contact VARCHAR(15) NOT NULL,
    Email VARCHAR(100),
    Profile_Pic VARCHAR(500),
    Emergency_Contact VARCHAR(15),
    Group_ID INT,
    password VARCHAR(255) NOT NULL,
    FOREIGN KEY (Group_ID) REFERENCES volunteer_name_group(group_id)
);

CREATE TABLE volunteer_name_group_members (
    group_id INT,
    NID VARCHAR(20) NOT NULL,
    PRIMARY KEY (group_id, NID),
    FOREIGN KEY (group_id) REFERENCES volunteer_name_group(group_id)
);

CREATE TABLE guardian (
    id INT,
    FOREIGN KEY (id) REFERENCES user(ID)
);

CREATE TABLE child (
    id INT PRIMARY KEY AUTO_INCREMENT,
    father_name VARCHAR(100) NOT NULL,
    mother_name VARCHAR(100) NOT NULL,
    guardian_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (id) REFERENCES user(ID)
);

-- Create relation table that depends on both child and guardian
CREATE TABLE relation (
    child_id INT,
    guardian_id INT,
    relation VARCHAR(20),
    PRIMARY KEY (child_id, guardian_id),
    FOREIGN KEY (child_id) REFERENCES child(id),
    FOREIGN KEY (guardian_id) REFERENCES guardian(id)
);

CREATE TABLE citizen_report (
    ID INT,
    anon_rep BOOLEAN NOT NULL,
    report_datetime DATETIME,
    title VARCHAR(100) NOT NULL,
    details TEXT,
    area VARCHAR(100) NOT NULL,
    media VARCHAR(500),
    PRIMARY KEY (ID, report_datetime, area),
    FOREIGN KEY (ID) REFERENCES user(ID)
);

CREATE TABLE Alert (
    Alert_ID INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(100) NOT NULL,
    Details TEXT,
    Status VARCHAR(10) NOT NULL,
    Location_URL VARCHAR(255),
    Alert_datetime DATETIME NOT NULL,
    Media VARCHAR(500),
    Area VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES user(ID)
);

CREATE TABLE log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    alert_id INT,
    log_datetime DATETIME NOT NULL,
    area VARCHAR(100) NOT NULL,
    title VARCHAR(100) NOT NULL,
    details TEXT,
    media VARCHAR(255),
    FOREIGN KEY (alert_id) REFERENCES alert(Alert_ID)
);

CREATE TABLE share_on_social (
    facebook VARCHAR(500),
    twitter VARCHAR(500),
    alert_id INT PRIMARY KEY,
    FOREIGN KEY (alert_id) REFERENCES alert(Alert_ID)
);


CREATE TABLE quick_respond_to (
    group_id INT,
    alert_id INT,
    PRIMARY KEY (group_id, alert_id),
    FOREIGN KEY (group_id) REFERENCES volunteer_name_group(group_id),
    FOREIGN KEY (alert_id) REFERENCES alert(Alert_ID)
);

CREATE TABLE thana (
    station_id int NOT NULL,
    station_name VARCHAR(50) NOT NULL,
    OC_name VARCHAR(50),
    OC_contact VARCHAR(15),
    password VARCHAR(255) NOT NULL,
    PRIMARY KEY (station_id, station_name)
);

CREATE TABLE thana_area (
    station_id int,
    thana_area VARCHAR(100),
    PRIMARY KEY (station_id, thana_area),
    FOREIGN KEY (station_id) REFERENCES thana(station_id)
);