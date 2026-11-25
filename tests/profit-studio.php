<?php
/** Run with PHP CLI. No WordPress or database needed. */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../local/mu-plugins/tiron/profit-calculator.php';

function expect_profit( $expected, $actual, string $case ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $case . ' expected ' . var_export( $expected, true ) . ' got ' . var_export( $actual, true ) );
	}
}

[ , $blank, $errors ] = tiron_profit_evaluate( [] );
expect_profit( null, $blank, 'Blank estimate' );
expect_profit( [], $errors, 'Blank form valid' );

[ , $zero, $errors ] = tiron_profit_evaluate( [ 'materials' => [ 'white' => [ 'bought' => '0', 'used' => '0', 'paid' => '0' ] ] ] );
expect_profit( [], $errors, 'Zero quantity and no purchase' );
expect_profit( 0, $zero['production'], 'Zero-cost component' );
foreach ( [ 'used', 'paid', 'freight' ] as $field ) {
	[ , $bad, $errors ] = tiron_profit_evaluate( [ 'materials' => [ 'white' => [ 'bought' => '0', $field => '1' ] ] ] );
	expect_profit( null, $bad, 'No positive values with zero denominator' );
	expect_profit( 1, count( $errors ), 'Zero denominator error' );
}

[ , $example, $errors ] = tiron_profit_evaluate( [
	'materials' => [
		'white' => [ 'bought' => '1000', 'paid' => '85', 'freight' => '15', 'used' => '120' ],
		'body' => [ 'bought' => '1,5', 'paid' => '90', 'used' => '0.250' ],
		'led' => [ 'bought' => '5', 'paid' => '50', 'freight' => '10', 'used' => '0,4' ],
		'packaging' => [ 'bought' => '10', 'paid' => '40', 'freight' => '5', 'used' => '1' ],
	],
	'costs' => [
		'electricity' => '2,20', 'minutes' => '30', 'hourly' => '45', 'packaging_extra' => '1,50',
		'overhead' => '3,50', 'scrap' => '20', 'shipping' => '20', 'fee_fixed' => '1,20',
		'fee_percent' => '2', 'advertising' => '35',
	],
] );
expect_profit( [], $errors, 'Illustrative data valid' );
expect_profit( 120000, $example['components']['white'], 'Purchase freight allocated by usage' );
expect_profit( 150000, $example['components']['body'], 'Fractional kilograms' );
expect_profit( 60000, $example['parts']['packaging'], 'BOM packaging plus separately allocated extras' );
expect_profit( 141250, $example['parts']['scrap'], '20% loss applied to materials before packing, energy and labour' );
expect_profit( 801250, $example['production'], 'Production before rounding to cents' );
expect_profit( [ 6 => 4807500, 10 => 8012500, 20 => 16025000 ], $example['batches'], 'Batch multiplication before display rounding' );
expect_profit( [ 249, 269, 299 ], array_keys( $example['scenarios'] ), 'Fixed scenarios' );
expect_profit( 1426950, $example['scenarios'][249]['break_even'], 'Break-even acquisition cost' );
expect_profit( 1076950, $example['scenarios'][249]['contribution'], 'Contribution scenario 249' );
expect_profit( 1272950, $example['scenarios'][269]['contribution'], 'Contribution scenario 269' );
expect_profit( 1566950, $example['scenarios'][299]['contribution'], 'Contribution scenario 299' );

[ , $negative ] = tiron_profit_evaluate( [ 'costs' => [ 'overhead' => '300', 'advertising' => '20' ] ] );
expect_profit( -510000, $negative['scenarios'][249]['gross'], 'Negative gross margin' );
expect_profit( -2048, $negative['scenarios'][249]['gross_rate'], 'Negative gross margin percentage' );
expect_profit( -710000, $negative['scenarios'][249]['contribution'], 'Negative contribution' );
expect_profit( -510000, $negative['scenarios'][249]['break_even'], 'Loss before advertising' );

foreach ( [ 'scrap' => '100', 'fee_percent' => '100,01', 'fee_fixed' => '1.001', 'advertising' => '-1' ] as $key => $value ) {
	[ , $bad, $errors ] = tiron_profit_evaluate( [ 'costs' => [ $key => $value ] ] );
	expect_profit( null, $bad, 'Reject ' . $key );
	expect_profit( 1, count( $errors ), 'Specific validation error ' . $key );
}
echo "PASS Profit Studio decimal arithmetic, batches, loss, zero inputs, shipping allocation and negative margins\n";
