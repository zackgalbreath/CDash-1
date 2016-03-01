<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__) . '/cdash_test_case.php');
require_once('include/common.php');
require_once('include/pdo.php');
require_once('models/build.php');

class BuildGetDateTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildGetDate()
    {
        $retval = 0;
        $build = new Build();
        $build->Id = 1;
        $build->ProjectId = 1;
        $build->Filled = true;

        // Test the case where the project's start time is in the evening.
        $build->StartTime = date("Y-m-d H:i:s", strtotime('2009-02-23 19:59:59'));

        $expected_date = '2009-02-23';
        $date = $build->GetDate();
        if ($build->GetDate() !== $expected_date) {
            $this->fail("Evening case: expected $expected_date, found $date");
            $retval = 1;
        }

        $build->StartTime = date("Y-m-d H:i:s", strtotime('2009-02-23 20:00:00'));

        $expected_date = '2009-02-24';
        $date = $build->GetDate();
        if ($build->GetDate() !== $expected_date) {
            $this->fail("Evening case: expected $expected_date, found $date");
            $retval = 1;
        }

        // Test the case where the project's start time is in the morning.
        $row = pdo_single_row_query("SELECT nightlytime FROM project WHERE id=1");
        $original_nightlytime = $row['nightlytime'];
        pdo_query("UPDATE project SET nightlytime = '09:00:00 EST' WHERE id=1");

        $build->StartTime = date("Y-m-d H:i:s", strtotime('2009-02-23 08:59:59'));
        $expected_date = '2009-02-22';
        $date = $build->GetDate();
        if ($build->GetDate() !== $expected_date) {
            $this->fail("Morning case: expected $expected_date, found $date");
            $retval = 1;
        }

        $build->StartTime = date("Y-m-d H:i:s", strtotime('2009-02-23 09:00:00'));
        $expected_date = '2009-02-23';
        $date = $build->GetDate();
        if ($build->GetDate() !== $expected_date) {
            $this->fail("Morning case: expected $expected_date, found $date");
            $retval = 1;
        }

        pdo_query("UPDATE project SET nightlytime = '$original_nightlytime' WHERE id=1");

        if ($retval === 0) {
            $this->pass('Tests passed');
        }
        return $retval;
    }
}
