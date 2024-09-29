<?php

// Check for correct command-line arguments
if ($argc !== 3) {
    die("Usage: php {$_SERVER['argv'][0]} <input file> <output file>\n");
}

$infile = $_SERVER['argv'][1];
$outfile = $_SERVER['argv'][2];

echo "Processing $infile to $outfile\n";

// Read and process input file
$data = "ob_end_clean();?>" . php_strip_whitespace($infile);

// Compress and encode data
$compressedData = gzcompress($data, 9);
$encodedData = base64_encode($compressedData);

// Generate output PHP code
$outputCode = <<<EOD
<?php
ob_start();
\$a = '$encodedData';
eval(gzuncompress(base64_decode(\$a)));
\$v = ob_get_contents();
ob_end_clean();
?>
EOD;

// Write output to file
if (file_put_contents($outfile, $outputCode) === false) {
    die("Error: Unable to write to output file.\n");
}

echo "Compression complete. Output written to $outfile\n";