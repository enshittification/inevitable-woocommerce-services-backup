/** @format */

/**
 * External dependencies
 */
import { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';

/**
 * Internal dependencies
 */
import { fetchSettings } from 'woocommerce/woocommerce-services/state/endshipper-address/actions';
import {
	areSettingsLoaded,
	areSettingsFetching,
	areSettingsErrored,
} from 'woocommerce/woocommerce-services/state/endshipper-address/selectors';

class QueryEndShipperAddress extends Component {
	fetch( props ) {
		const { siteId, loaded, fetching, error } = props;
		if ( ! loaded && ! fetching && ! error ) {
			this.props.fetchSettings( siteId );
		}
	}

	UNSAFE_componentWillMount() {
		this.fetch( this.props );
	}

	UNSAFE_componentWillReceiveProps( nextProps ) {
		this.fetch( nextProps );
	}

	render() {
		return null;
	}
}

QueryEndShipperAddress.propTypes = {
	siteId: PropTypes.number.isRequired,
};

export default connect(
	state => ( {
		loaded: areSettingsLoaded( state ),
		fetching: areSettingsFetching( state ),
		error: areSettingsErrored( state ),
	} ),
	dispatch =>
		bindActionCreators(
			{
				fetchSettings,
			},
			dispatch
		)
)( QueryEndShipperAddress );
