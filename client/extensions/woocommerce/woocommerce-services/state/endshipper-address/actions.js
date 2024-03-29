/** @format */
/**
 * Internal dependencies
 */
import * as api from '../../api';
import {
	WOOCOMMERCE_SERVICES_ENDSHIPPER_INIT_FORM,
	WOOCOMMERCE_SERVICES_ENDSHIPPER_RESTORE_PRISTINE,
	WOOCOMMERCE_SERVICES_ENDSHIPPER_SET_FORM_DATA_VALUE,
	WOOCOMMERCE_SERVICES_ENDSHIPPER_SET_FORM_META_PROPERTY,
	WOOCOMMERCE_SERVICES_ENDSHIPPER_OPEN_ADD_CARD_DIALOG,
	WOOCOMMERCE_SERVICES_ENDSHIPPER_CLOSE_ADD_CARD_DIALOG,
} from '../action-types';
import { getEndShipperAddressFormData } from './selectors';

export const initForm = ( siteId, storeOptions, formData, formMeta, userMeta ) => {
	return {
		type: WOOCOMMERCE_SERVICES_ENDSHIPPER_INIT_FORM,
		siteId,
		storeOptions,
		formData,
		formMeta,
		userMeta,
	};
};

export const setFormDataValue = ( siteId, key, value ) => ( {
	type: WOOCOMMERCE_SERVICES_ENDSHIPPER_SET_FORM_DATA_VALUE,
	siteId,
	key,
	value,
} );

export const setFormMetaProperty = ( siteId, key, value ) => {
	return {
		type: WOOCOMMERCE_SERVICES_ENDSHIPPER_SET_FORM_META_PROPERTY,
		siteId,
		key,
		value,
	};
};

export const fetchSettings = siteId => dispatch => {
	dispatch( setFormMetaProperty( siteId, 'isFetching', true ) );

	api
		.get( siteId, api.url.accountSettings )
		.then( ( { storeOptions, formMeta, formData, userMeta } ) => {
			dispatch( initForm( siteId, storeOptions, formData, formMeta, userMeta ) );
		} )
		.catch( error => {
			dispatch( setFormMetaProperty( siteId, 'isFetchError', true ) );
			console.error( error ); // eslint-disable-line no-console
		} )
		.then( () => dispatch( setFormMetaProperty( siteId, 'isFetching', false ) ) );
};

export const submit = ( siteId, onSaveSuccess, onSaveFailure ) => ( dispatch, getState ) => {
	dispatch( setFormMetaProperty( siteId, 'isSaving', true ) );
	dispatch( setFormMetaProperty( siteId, 'pristine', true ) );
	api
		.post( siteId, api.url.accountSettings, getEndShipperAddressFormData( getState() ) )
		.then( onSaveSuccess )
		.catch( err => {
			dispatch( setFormMetaProperty( siteId, 'pristine', false ) );
			return onSaveFailure( err );
		} )
		.then( () => {
			dispatch( setFormMetaProperty( siteId, 'isSaving', false ) );
		} );
};

export const restorePristineSettings = siteId => {
	return {
		type: WOOCOMMERCE_SERVICES_ENDSHIPPER_RESTORE_PRISTINE,
		siteId,
	};
};

export const openAddCardDialog = siteId => {
	return { type: WOOCOMMERCE_SERVICES_ENDSHIPPER_OPEN_ADD_CARD_DIALOG, siteId };
};

export const closeAddCardDialog = siteId => {
	return { type: WOOCOMMERCE_SERVICES_ENDSHIPPER_CLOSE_ADD_CARD_DIALOG, siteId };
};
