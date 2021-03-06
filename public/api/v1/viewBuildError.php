<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

$noforcelogin = 1;
include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include_once 'include/common.php';
include 'public/login.php';
include_once 'include/repository.php';
include 'include/version.php';
require_once 'models/build.php';

@$buildid = $_GET['buildid'];
if ($buildid != null) {
    $buildid = pdo_real_escape_numeric($buildid);
}

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$response = begin_JSON_response();
$response['title'] = 'CDash : View Build Errors';

// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    $response['error'] = 'Not a valid buildid!';
    echo json_encode($response);
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$start = microtime_float();

$build_query = "SELECT build.id, build.projectid, build.siteid, build.type,
                       build.name, build.starttime, buildupdate.revision
                FROM build
                LEFT JOIN build2update ON (build2update.buildid = build.id)
                LEFT JOIN buildupdate ON (buildupdate.id = build2update.updateid)
                WHERE build.id = '$buildid'";
$build_array = pdo_fetch_array(pdo_query($build_query));

if (empty($build_array)) {
    $response['error'] = 'This build does not exist. Maybe it has been deleted.';
    echo json_encode($response);
    return;
}

$projectid = $build_array['projectid'];
$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if (pdo_num_rows($project) > 0) {
    $project_array = pdo_fetch_array($project);
    $projectname = $project_array['name'];
}

if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $project_array['id'], 1)) {
    $response['requirelogin'] = '1';
    echo json_encode($response);
    return;
}

$response['title'] = "CDash : $projectname";
$siteid = $build_array['siteid'];
$buildtype = $build_array['type'];
$buildname = $build_array['name'];
$starttime = $build_array['starttime'];
$revision = $build_array['revision'];

$date = get_dashboard_date_from_build_starttime($build_array['starttime'], $project_array['nightlytime']);
get_dashboard_JSON_by_name($projectname, $date, $response);

$menu = array();
$menu['back'] = 'index.php?project=' . urlencode($projectname) . '&date=' . $date;

$build = new Build();
$build->Id = $buildid;
$previous_buildid = $build->GetPreviousBuildId();
$current_buildid = $build->GetCurrentBuildId();
$next_buildid = $build->GetNextBuildId();

if ($previous_buildid > 0) {
    $menu['previous'] = "viewBuildError.php?buildid=$previous_buildid";
} else {
    $menu['noprevious'] = 1;
}

$menu['current'] = "viewBuildError.php?buildid=$current_buildid";

if ($next_buildid > 0) {
    $menu['next'] = "viewBuildError.php?buildid=$next_buildid";
} else {
    $menu['nonext'] = 1;
}

$response['menu'] = $menu;

// Build
$build_response = array();
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$build_response['site'] = $site_array['name'];
$build_response['siteid'] = $siteid;
$build_response['buildname'] = $build_array['name'];
$build_response['starttime'] =
    date(FMT_DATETIMETZ, strtotime($build_array['starttime'] . 'UTC'));
$build_response['buildid'] = $build_array['id'];
$response['build'] = $build_response;

@$type = $_GET['type'];
if ($type != null) {
    $type = pdo_real_escape_numeric($type);
}
if (!isset($type)) {
    $type = 0;
}

// Set the error
if ($type == 0) {
    $response['errortypename'] = 'Error';
    $response['nonerrortypename'] = 'Warning';
    $response['nonerrortype'] = 1;
} else {
    $response['errortypename'] = 'Warning';
    $response['nonerrortypename'] = 'Error';
    $response['nonerrortype'] = 0;
}

$errors_response = array();

if (isset($_GET['onlydeltan'])) {
    // Build error table
    $errors = pdo_query(
        'SELECT * FROM
      (SELECT * FROM builderror
        WHERE buildid=' . $previous_buildid . ' AND type=' . $type . ') AS builderrora
      LEFT JOIN (SELECT crc32 AS crc32b FROM builderror
        WHERE buildid=' . $buildid . ' AND type=' . $type . ') AS builderrorb
        ON builderrora.crc32=builderrorb.crc32b
      WHERE builderrorb.crc32b IS NULL');

    $errorid = 0;
    while ($error_array = pdo_fetch_array($errors)) {
        $error_response = array();
        $error_response['id'] = $errorid;
        $error_response['new'] = -1;
        $error_response['logline'] = $error_array['logline'];
        $error_response['text'] = $error_array['text'];
        $error_response['sourcefile'] = $error_array['sourcefile'];
        $error_response['sourceline'] = $error_array['sourceline'];
        $error_response['precontext'] = $error_array['precontext'];
        $error_response['postcontext'] = $error_array['postcontext'];

        $projectCvsUrl = $project_array['cvsurl'];
        $file = basename($error_array['sourcefile']);
        $directory = dirname($error_array['sourcefile']);
        $cvsurl =
            get_diff_url($projectid, $projectCvsUrl, $directory, $file, $revision);

        $error_response['cvsurl'] = $cvsurl;
        $errorid++;

        $errors_response[] = $error_response;
    }

    // Get buildfailures that occurred yesterday and not today.
    $current_failures = array();
    $previous_failures = array();

    $query =
        "SELECT bf.detailsid FROM buildfailure AS bf
     LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
     WHERE bf.buildid=$buildid AND bfd.type=$type";
    $result = pdo_query($query);
    add_last_sql_error('viewBuildError onlydeltan', 0, $buildid);
    while ($row = pdo_fetch_array($result)) {
        $current_failures[] = $row['detailsid'];
    }

    $query =
        "SELECT bf.detailsid FROM buildfailure AS bf
     LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
     WHERE bf.buildid=$previous_buildid AND bfd.type=$type";
    $result = pdo_query($query);
    add_last_sql_error('viewBuildError onlydeltan', 0, $buildid);
    while ($row = pdo_fetch_array($result)) {
        $previous_failures[] = $row['detailsid'];
    }

    foreach ($previous_failures as $failure) {
        if (!in_array($failure, $current_failures)) {
            $error_array = pdo_single_row_query(
                "SELECT bf.id, bfd.language, bf.sourcefile, bfd.targetname, bfd.outputfile,
                bfd.outputtype, bf.workingdirectory, bfd.stderror, bfd.stdoutput,
                bfd.exitcondition
         FROM buildfailure AS bf
         LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
         WHERE bf.buildid=$previous_buildid AND bfd.id=$failure");
            add_last_sql_error('viewBuildError onlydeltan', $projectid);

            if (!$error_array) {
                add_log('Expected results not returned', 'viewBuildError onlydeltan',
                    LOG_ERR, $projectid, 0, $buildid);
                continue;
            }

            $error_response = array();
            $error_response['id'] = $errorid;
            $error_response['language'] = $error_array['language'];
            $error_response['sourcefile'] = $error_array['sourcefile'];
            $error_response['targetname'] = $error_array['targetname'];
            $error_response['outputfile'] = $error_array['outputfile'];
            $error_response['outputtype'] = $error_array['outputtype'];
            $error_response['workingdirectory'] = $error_array['workingdirectory'];

            $buildfailureid = $error_array['id'];
            $arguments = pdo_query(
                "SELECT bfa.argument FROM buildfailureargument AS bfa,
                buildfailure2argument AS bf2a
         WHERE bf2a.buildfailureid='$buildfailureid' AND
               bf2a.argumentid=bfa.id ORDER BY bf2a.place ASC");

            $i = 0;
            $arguments_response = array();
            while ($argument_array = pdo_fetch_array($arguments)) {
                if ($i == 0) {
                    $error_response['argumentfirst'] = $argument_array['argument'];
                } else {
                    $arguments_response[] = $argument_array['argument'];
                }
                $i++;
            }
            $error_response['arguments'] = $arguments_response;

            get_labels_xml_from_query_results(
                "SELECT text FROM label, label2buildfailure
         WHERE label.id=label2buildfailure.labelid AND
               label2buildfailure.buildfailureid='$buildfailureid'
         ORDER BY text ASC", $error_response);

            $error_response['stderror'] = $error_array['stderror'];
            $rows = substr_count($error_array['stderror'], "\n") + 1;
            if ($rows > 10) {
                $rows = 10;
            }
            $error_response['stderrorrows'] = $rows;

            $error_response['stdoutput'] = $error_array['stdoutput'];
            $rows = substr_count($error_array['stdoutput'], "\n") + 1;
            if ($rows > 10) {
                $rows = 10;
            }
            $error_response['stdoutputrows'] = $rows;

            $error_response['exitcondition'] = $error_array['exitcondition'];

            if (isset($error_array['sourcefile'])) {
                $projectCvsUrl = $project_array['cvsurl'];
                $file = basename($error_array['sourcefile']);
                $directory = dirname($error_array['sourcefile']);
                $cvsurl =
                    get_diff_url($projectid, $projectCvsUrl, $directory, $file, $revision);
                $error_response['cvsurl'] = $cvsurl;
            }
            $errorid++;
            $errors_response[] = $error_response;
        }
    }
} else {
    $extrasql = '';
    if (isset($_GET['onlydeltap'])) {
        $extrasql = " AND newstatus='1'";
    }

    // Build error table
    $errors = pdo_query("SELECT * FROM builderror WHERE buildid='$buildid' AND type='$type'" . $extrasql . ' ORDER BY logline ASC');
    $errorid = 0;
    while ($error_array = pdo_fetch_array($errors)) {
        $error_response = array();
        $error_response['id'] = $errorid;
        $error_response['new'] = $error_array['newstatus'];
        $error_response['logline'] = $error_array['logline'];

        $projectCvsUrl = $project_array['cvsurl'];
        $text = $error_array['text'];

        // Detect if the source directory has already been replaced by CTest with /.../
        $pattern = '&/.../(.*?)/&';
        $matches = array();
        preg_match($pattern, $text, $matches);
        if (sizeof($matches) > 1) {
            $file = $error_array['sourcefile'];
            $directory = $matches[1];
        } else {
            $file = basename($error_array['sourcefile']);
            $directory = dirname($error_array['sourcefile']);
        }

        $cvsurl = get_diff_url($projectid, $projectCvsUrl, $directory, $file, $revision);

        $error_response['cvsurl'] = $cvsurl;
        // when building without launchers, CTest truncates the source dir to /.../
        // use this pattern to linkify compiler output.
        $precontext = linkify_compiler_output($projectCvsUrl, "/\.\.\.", $revision, $error_array['precontext']);
        $text = linkify_compiler_output($projectCvsUrl, "/\.\.\.", $revision, $error_array['text']);
        $postcontext = linkify_compiler_output($projectCvsUrl, "/\.\.\.", $revision, $error_array['postcontext']);

        $error_response['precontext'] = $precontext;
        $error_response['text'] = $text;
        $error_response['postcontext'] = $postcontext;
        $error_response['sourcefile'] = $error_array['sourcefile'];
        $error_response['sourceline'] = $error_array['sourceline'];

        $errorid++;
        $errors_response[] = $error_response;
    }

    // Build failure table
    $errors = pdo_query(
        "SELECT bf.id, bfd.language, bf.sourcefile, bfd.targetname, bfd.outputfile,
            bfd.outputtype, bf.workingdirectory, bfd.stderror, bfd.stdoutput,
            bfd.exitcondition
     FROM buildfailure AS bf
     LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
     WHERE bf.buildid='$buildid' AND bfd.type='$type'" . $extrasql . '
     ORDER BY bf.id ASC');
    add_last_sql_error('viewBuildError get_failures', $projectid);
    while ($error_array = pdo_fetch_array($errors)) {
        $error_response = array();
        $error_response['id'] = $errorid;
        $error_response['language'] = $error_array['language'];
        $error_response['sourcefile'] = $error_array['sourcefile'];
        $error_response['targetname'] = $error_array['targetname'];
        $error_response['outputfile'] = $error_array['outputfile'];
        $error_response['outputtype'] = $error_array['outputtype'];
        $error_response['workingdirectory'] = $error_array['workingdirectory'];

        $buildfailureid = $error_array['id'];
        $arguments = pdo_query(
            "SELECT bfa.argument FROM buildfailureargument AS bfa,
              buildfailure2argument AS bf2a
       WHERE bf2a.buildfailureid='$buildfailureid' AND bf2a.argumentid=bfa.id
       ORDER BY bf2a.place ASC");
        $i = 0;
        $arguments_response = array();
        while ($argument_array = pdo_fetch_array($arguments)) {
            if ($i == 0) {
                $error_response['argumentfirst'] = $argument_array['argument'];
            } else {
                $arguments_response[] = $argument_array['argument'];
            }
            $i++;
        }
        $error_response['arguments'] = $arguments_response;

        get_labels_JSON_from_query_results(
            "SELECT text FROM label, label2buildfailure
       WHERE label.id=label2buildfailure.labelid AND
             label2buildfailure.buildfailureid='$buildfailureid'
       ORDER BY text ASC", $error_response);

        $stderror = $error_array['stderror'];
        $stdoutput = $error_array['stdoutput'];

        if (isset($error_array['sourcefile'])) {
            $projectCvsUrl = $project_array['cvsurl'];
            $file = basename($error_array['sourcefile']);
            $directory = dirname($error_array['sourcefile']);
            $cvsurl =
                get_diff_url($projectid, $projectCvsUrl, $directory, $file, $revision);
            $error_response['cvsurl'] = $cvsurl;

            $source_dir = get_source_dir($projectid, $projectCvsUrl, $directory);
            if ($source_dir !== null) {
                $stderror = linkify_compiler_output($projectCvsUrl, $source_dir,
                    $revision, $stderror);
                $stdoutput = linkify_compiler_output($projectCvsUrl, $source_dir,
                    $revision, $stdoutput);
            }
        }

        if ($stderror) {
            $error_response['stderror'] = $stderror;
        }
        if ($stdoutput) {
            $error_response['stdoutput'] = $stdoutput;
        }
        $error_response['exitcondition'] = $error_array['exitcondition'];
        $errorid++;
        $errors_response[] = $error_response;
    }
}

$response['errors'] = $errors_response;
$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);

echo json_encode(cast_data_for_JSON($response));
