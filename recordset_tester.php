<?php

define('CLI_SCRIPT', true);
require_once('../../../config.php');


$size = 10000; #start sise
$ramp = 1.5;  # how quickly is ramps up
$delay = 1; # sleep for 1 second between runs
$limit = 1024 * 1024 * 20; # db rows maximum

$use_rs = true;  # use get_recordset
#$use_rs = false; # use get_records

function mem() {
    $cmd = "ps -p " .getmypid()." -o vsz"; # wow! super horrible :)
    $mem1 = `$cmd`;
    preg_match_all('!\d+!', $mem1, $matches);
    $mem2 = $matches[0][0] * 1024;
    return $mem2;

    // return memory_get_usage(false); # this is base php memory but excludes pg client memory
    // return memory_get_usage(true); # this is base php memory but should include other stuff??
    // return memory_get_peak_usage(false); # this is peak php memory
    // return memory_get_peak_usage(true); # this is peak php memory
}

function convert($size) {
    if ($size == 0) {
        return '0';
    }

    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),0) . $unit[$i];
}

$memory_base = mem();

print "top -p ".getmypid() ." # cut and paste this\n";
// sleep(3); # so you can cut and paste the top command

printf("Start php memory     = %s \n", convert(memory_get_usage()));
printf("Start process memory = %s \n", convert($memory_base));

echo "Size             RS      Loop     After Count\n";

for(;;) {

    $sql = "
SELECT
    numbers AS id,
    concat('content', numbers) AS content
FROM
    generate_series(1, $size) AS numbers
;
";

    $mem_before_rs = mem();

    if ($use_rs) {
        $rs = $DB->get_recordset_sql($sql);
    } else {
        $rs = $DB->get_records_sql($sql);
    }

    $mem_before_loop = mem();

    $count = 0;

    foreach ($rs as $record) {
        $count += $record->id;
    }

    sleep($delay);

    $mem_after_loop = mem();


    printf("%-9d %9s %9s %9s %d\n",
        $size,
        // convert($mem_before_rs   - $memory_base),
        convert($mem_before_rs),
        // convert($mem_before_loop - $memory_base),
        convert($mem_before_loop),
        // convert($mem_after_loop  - $memory_base),
        convert($mem_after_loop),
        $count
    );

    if ($use_rs) {
        $rs->close();
    }

    $size = floor($size * $ramp);
    if ($size > $limit) {
        break;
    }
}

$memory_base = mem();

printf("Start php memory   = %s \n", convert(memory_get_usage()));
printf("End process memory = %s \n", convert($memory_base));

