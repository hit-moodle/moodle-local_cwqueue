<?php

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
//                      Online Judge for Moodle                          //
//        https://github.com/hit-moodle/moodle-local_onlinejudge         //
//                                                                       //
// Copyright (C) 2009 onwards  Sun Zhigang  http://sunner.cn             //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/**
 * Online Judge cron job
 * 
 * @package   local_onlinejudge
 * @copyright 2011 Sun Zhigang (http://sunner.cn)
 * @author    Sun Zhigang
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$CFG->debug = DEBUG_MINIMAL; // suppress debug info of download_file_content(). The source server is always down regulary

require_once($CFG->libdir.'/filelib.php');
require_once('locallib.php');

if (!cwq_is_working()) {
    die;
}

$cwc_url = 'http://cwc.hit.edu.cn/server.asp';

$raw_data = download_file_content($cwc_url);
if ($raw_data) {
    $data = explode('#', $raw_data);
    $current = $data[0];
    $last = $data[1];
    if ($current != 0 and $last != 0 and $last >= $current) {
        $r = new stdClass();
        $now = time();
        $r->time = $now;
        $r->year    = date('Y', $now);
        $r->month   = date('N', $now);
        $r->day     = date('j', $now);
        $r->dayofweek = date('N', $now);
        $r->minutes = cwq_vectorize_time($now);
        $r->current = $current - BASE_NUMBER;
        $r->last = $last - BASE_NUMBER;
        $DB->insert_record('cwqueue_status', $r);
    }
}

