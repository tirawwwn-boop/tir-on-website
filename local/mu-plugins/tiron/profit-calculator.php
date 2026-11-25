<?php
/** Calculations in integer ten-thousandths of RON. No WooCommerce writes or tax assumptions. */
defined( 'ABSPATH' ) || exit;

function tiron_profit_components(): array {
	return [
		'white' => 'Filament alb pentru litofan',
		'body' => 'Filament pentru ramă și bază',
		'led' => 'Bandă LED',
		'power' => 'Alimentator și componente USB-C',
		'button' => 'Buton de pornire iluminat',
		'wiring' => 'Cabluri și conectori',
		'diffuser' => 'Difuzor și elemente de fixare',
		'packaging' => 'Ambalaj premium',
		'consumables' => 'Alte consumabile',
	];
}

function tiron_profit_cost_fields(): array {
	// Each field specifies its label, decimal precision, and maximum in scaled units.
	return [
		'electricity' => [ 'Energie / Luna (RON)', 2, 10000000 ],
		'minutes' => [ 'Manoperă / Luna (minute)', 2, 1000000 ],
		'hourly' => [ 'Tarif de manoperă (RON/oră)', 2, 10000000 ],
		'packaging_extra' => [ 'Ambalaj suplimentar / Luna (RON)', 2, 10000000 ],
		'overhead' => [ 'Regie alocată / Luna (RON)', 2, 10000000 ],
		'scrap' => [ 'Rată de rebuturi (%)', 2, 9900 ],
		'shipping' => [ 'Livrare suportată / comandă (RON)', 2, 10000000 ],
		'fee_fixed' => [ 'Comision fix / comandă (RON)', 2, 10000000 ],
		'fee_percent' => [ 'Comision procentual (%)', 2, 10000 ],
		'advertising' => [ 'Publicitate / comandă (RON)', 2, 10000000 ],
	];
}

function tiron_profit_divide( int $amount, int $divisor ): int {
	return intdiv( $amount + intdiv( $divisor, 2 ), $divisor );
}

/** Accept the Romanian decimal comma or a decimal point, never a grouping separator. */
function tiron_profit_number( string $text, int $precision, int $max ): int {
	if ( '' === $text ) {
		return 0;
	}
	if ( ! preg_match( '/\A(\d{1,6})(?:[.,](\d{1,' . $precision . '}))?\z/D', $text, $parts ) ) {
		throw new InvalidArgumentException( 'Introdu un număr mai mare sau egal cu zero, cu cel mult ' . $precision . ' zecimale (de ex. 12,50).' );
	}
	$value = (int) $parts[1] * ( 10 ** $precision ) + (int) str_pad( $parts[2] ?? '', $precision, '0' );
	if ( $value > $max ) {
		throw new InvalidArgumentException( 'Valoarea depășește limita acestui câmp.' );
	}
	return $value;
}

/** Validate the complete form atomically and preserve blank fields for editing. */
function tiron_profit_evaluate( array $input ): array {
	$data = [ 'materials' => [], 'costs' => [] ];
	$errors = [];
	foreach ( array_keys( $data ) as $group ) {
		if ( isset( $input[ $group ] ) && ! is_array( $input[ $group ] ) ) {
			$errors[] = 'Date de calcul nevalide.';
			unset( $input[ $group ] );
		}
	}
	$has_values = false;
	$read = static function ( $raw, int $length, string $label ) use ( &$errors ): string {
		if ( ! is_string( $raw ) || strlen( $raw ) > $length ) {
			$errors[] = $label . ': câmp nevalid sau prea lung.';
			return '';
		}
		return trim( $raw );
	};
	$parse = static function ( string $text, int $precision, int $max, string $label ) use ( &$errors, &$has_values ): int {
		$has_values = $has_values || '' !== $text;
		try {
			return tiron_profit_number( $text, $precision, $max );
		} catch ( InvalidArgumentException $error ) {
			$errors[] = $label . ': ' . $error->getMessage();
			return 0;
		}
	};
	$amounts = [];
	$labels = [
		'bought' => 'Cantitate cumpărată',
		'paid' => 'Total plătit',
		'freight' => 'Transport alocat',
		'used' => 'Consum pe Luna',
	];
	foreach ( tiron_profit_components() as $key => $label ) {
		$row = $input['materials'][ $key ] ?? [];
		if ( ! is_array( $row ) ) {
			$errors[] = $label . ': rând nevalid.';
			$row = [];
		}
		$fields = [
			'supplier' => $read( $row['supplier'] ?? '', 160, $label . ' – furnizor' ),
			'unit' => $read( $row['unit'] ?? '', 24, $label . ' – unitate' ),
		];
		$values = [];
		foreach ( $labels as $name => $caption ) {
			$field = $label . ' – ' . $caption;
			$fields[ $name ] = $read( $row[ $name ] ?? '', 16, $field );
			$values[ $name ] = $parse( $fields[ $name ], in_array( $name, [ 'bought', 'used' ], true ) ? 3 : 2, 10000000, $field );
		}
		$data['materials'][ $key ] = $fields;
		$amounts[ $key ] = 0;
		if ( ! $values['bought'] && ( $values['used'] || $values['paid'] || $values['freight'] ) ) {
			$errors[] = $label . ': introdu o cantitate cumpărată mai mare de zero pentru a aloca achiziția sau consumul.';
			continue;
		}
		if ( $values['bought'] ) {
			$amounts[ $key ] = tiron_profit_divide( ( $values['paid'] + $values['freight'] ) * 100 * $values['used'], $values['bought'] );
			if ( $amounts[ $key ] > 1000000000 ) {
				$errors[] = $label . ': costul calculat depășește 100.000 RON pe Luna. Verifică unitatea și consumul.';
			}
		}
	}
	$costs = [];
	foreach ( tiron_profit_cost_fields() as $key => [ $label, $precision, $max ] ) {
		$data['costs'][ $key ] = $read( $input['costs'][ $key ] ?? '', 16, $label );
		$costs[ $key ] = $parse( $data['costs'][ $key ], $precision, $max, $label );
	}

	if ( $errors || ! $has_values ) {
		return [ $data, null, $errors ];
	}

	$parts = [
		'materials' => array_sum( $amounts ) - $amounts['packaging'],
		'electricity' => $costs['electricity'] * 100,
		'labour' => tiron_profit_divide( $costs['minutes'] * $costs['hourly'], 60 ),
		'packaging' => $amounts['packaging'] + $costs['packaging_extra'] * 100,
		'overhead' => $costs['overhead'] * 100,
	];
	$at_risk = $parts['materials'] + $parts['electricity'] + $parts['labour'];
	$parts['scrap'] = tiron_profit_divide( $at_risk * $costs['scrap'], 10000 - $costs['scrap'] );
	$production = array_sum( $parts );
	$shipping = $costs['shipping'] * 100;
	$advertising = $costs['advertising'] * 100;
	$scenarios = [];
	foreach ( [ 249, 269, 299 ] as $price_ron ) {
		$price = $price_ron * 10000;
		$gross = $price - $production;
		$fee = $costs['fee_fixed'] * 100 + tiron_profit_divide( $price * $costs['fee_percent'], 10000 );
		$break_even = $gross - $shipping - $fee;
		$scenarios[ $price_ron ] = [
			'gross' => $gross,
			'gross_rate' => ( $gross < 0 ? -1 : 1 ) * tiron_profit_divide( abs( $gross ) * 10000, $price ),
			'shipping' => $shipping,
			'fee' => $fee,
			'advertising' => $advertising,
			'contribution' => $break_even - $advertising,
			'break_even' => $break_even,
		];
	}
	return [ $data, [
		'production' => $production,
		'components' => $amounts,
		'parts' => $parts,
		'batches' => [ 6 => 6 * $production, 10 => 10 * $production, 20 => 20 * $production ],
		'scenarios' => $scenarios,
	], [] ];
}
