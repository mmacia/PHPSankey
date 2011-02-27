<?php

require dirname(dirname(__FILE__)) . '/lib/SankeyGraph.php';

$inputs = array(120 => 'GPP', 92 => 'Ocean Assimilation');
$losses = array(45 => 'Ra',
                75 => 'Rh',
                90 => 'Ocean loss',
                1  => 'LULCC',
                6  => 'Fossil fuel emissions');

$sk = new SankeyGraph($inputs, $losses);
$sk->drawTo(dirname(dirname(__FILE__)).'/samples/output.png');

