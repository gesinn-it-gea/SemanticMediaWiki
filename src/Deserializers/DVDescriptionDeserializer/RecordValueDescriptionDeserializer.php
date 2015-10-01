<?php

namespace SMW\Deserializers\DVDescriptionDeserializer;

use SMWRecordValue as RecordValue;
use SMW\Query\Language\ThingDescription;
use SMW\Query\Language\Conjunction;
use SMW\Query\Language\SomeProperty;
use SMW\DataValueFactory;
use SMW\DIProperty;
use InvalidArgumentException;

/**
 * @private
 *
 * @license GNU GPL v2+
 * @since 2.3
 *
 * @author mwjames
 */
class RecordValueDescriptionDeserializer extends DescriptionDeserializer {

	/**
	 * @since 2.3
	 *
	 * {@inheritDoc}
	 */
	public function isDeserializerFor( $serialization ) {
		return $serialization instanceof RecordValue;
	}

	/**
	 * @since 2.3
	 *
	 * {@inheritDoc}
	 */
	public function deserialize( $value ) {

		if ( !is_string( $value ) ) {
			throw new InvalidArgumentException( 'value needs to be a string' );
		}

		if ( $value === '' ) {
			$this->addError( wfMessage( 'smw_novalues' )->text() );
			return new ThingDescription();
		}

		$subdescriptions = array();
		$values = $this->dataValue->getValuesFromString( $value );

		$valueIndex = 0; // index in value array
		$propertyIndex = 0; // index in property list

		foreach ( $this->dataValue->getPropertyDataItems() as $diProperty ) {

			// stop if there are no values left
			if ( !is_array( $values ) || !array_key_exists( $valueIndex, $values ) ) {
				break;
			}

			$description = $this->getDescriptionForProperty(
				$diProperty,
				$values,
				$valueIndex,
				$propertyIndex
			);

			if ( $description !== null ) {
				 $subdescriptions[] = $description;
			}

			++$propertyIndex;
		}

		if ( $subdescriptions === array() ) {
			$this->addError( wfMessage( 'smw_novalues' )->text() );
		}

		return $this->getDescriptionFor( $subdescriptions );
	}

	private function getDescriptionFor( $subdescriptions ){
		switch ( count( $subdescriptions ) ) {
			case 0:
			return new ThingDescription();
			case 1:
			return reset( $subdescriptions );
			default:
			return new Conjunction( $subdescriptions );
		}
	}

	private function getDescriptionForProperty( $diProperty, $values, &$valueIndex, $propertyIndex ){

		$values[$valueIndex] = str_replace( "-3B", ";", $values[$valueIndex] );
		$beforePrepareValue = $values[$valueIndex];

		$description = null;
		$comparator = SMW_CMP_EQ;

		$this->prepareValue( $values[$valueIndex], $comparator );

		// generating the DVs:
		if ( ( $values[$valueIndex] === '' ) || ( $values[$valueIndex] == '?' ) ) { // explicit omission
			$valueIndex++;
			return $description;
		}

		$dataValue = DataValueFactory::getInstance()->newPropertyObjectValue(
			$diProperty,
			$values[$valueIndex]
		);

		if ( $dataValue->isValid() ) { // valid DV: keep
			$description = new SomeProperty(
				$diProperty,
				$dataValue->getQueryDescription( $beforePrepareValue )
			);
			$valueIndex++;
		} elseif ( ( count( $values ) - $valueIndex ) == ( count( $this->dataValue->getProperties() ) - $propertyIndex ) ) {
			$this->addError( $dataValue->getErrors() );
			++$valueIndex;
		}

		return $description;
	}

}
