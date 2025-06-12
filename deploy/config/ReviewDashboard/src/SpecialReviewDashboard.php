<?php

namespace MediaWiki\Extension\ReviewDashboard;

use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Output\OutputPage;
use MediaWiki\Html\Html;
use MediaWiki\User\User;
use MediaWiki\Permissions\PermissionsError;
use MediaWiki\Session\UserNotLoggedIn;

class SpecialReviewDashboard extends SpecialPage {

	public function __construct() {
		parent::__construct( 'ReviewDashboard' );
	}

	public function execute( $subPage ) {
		$this->setHeaders();
		$this->outputHeader();
		$this->checkPermissions();

		$out = $this->getOutput();
		$out->addModules( 'ext.reviewdashboard' );
		
		// Pass user permission level to JavaScript
		$out->addJsConfigVars( 'reviewDashboardPermissions', $this->getUserPermissionLevel() );

		$this->showDashboard( $out );
	}

	private function showDashboard( OutputPage $out ) {
		$html = '';

		// Header
		$html .= Html::openElement( 'div', [ 'class' => 'review-dashboard-header' ] );
		$html .= Html::element( 'h1', [], $this->msg( 'reviewdashboard-title' )->text() );
		$html .= Html::element( 'p', [], $this->msg( 'reviewdashboard-description' )->text() );
		$html .= Html::closeElement( 'div' );

		// Configuration section
		$html .= Html::openElement( 'div', [ 'class' => 'review-dashboard-config' ] );
		$html .= Html::element( 'h2', [], $this->msg( 'reviewdashboard-config-title' )->text() );

		// SPARQL endpoint input
		$html .= Html::openElement( 'div', [ 'class' => 'form-group' ] );
		$html .= Html::element( 'label', [ 'for' => 'sparql-endpoint' ],
			$this->msg( 'reviewdashboard-sparql-endpoint' )->text() );
		$html .= Html::input( 'sparql-endpoint', '', 'url', [
			'id' => 'sparql-endpoint',
			'placeholder' => 'https://query.wikibase.local/sparql',
			'class' => 'form-control'
		] );
		$html .= Html::closeElement( 'div' );

		// Wikibase URL input
		$html .= Html::openElement( 'div', [ 'class' => 'form-group' ] );
		$html .= Html::element( 'label', [ 'for' => 'wikibase-url' ],
			$this->msg( 'reviewdashboard-wikibase-url' )->text() );
		$html .= Html::input( 'wikibase-url', '', 'url', [
			'id' => 'wikibase-url',
			'placeholder' => 'https://wikibase.local',
			'class' => 'form-control'
		] );
		$html .= Html::closeElement( 'div' );

		// Load button
		$html .= Html::element( 'button', [
			'id' => 'load-items-btn',
			'class' => 'btn btn-primary'
		], $this->msg( 'reviewdashboard-load-items' )->text() );

		$html .= Html::closeElement( 'div' );

		// Message area
		$html .= Html::element( 'div', [ 'id' => 'review-messages' ], '' );

		// Results area
		$html .= Html::openElement( 'div', [ 'id' => 'review-results', 'style' => 'display: none;' ] );
		$html .= Html::openElement( 'div', [ 'class' => 'review-results-header' ] );
		$html .= Html::element( 'h2', [], $this->msg( 'reviewdashboard-results-title' )->text() );
		$html .= Html::element( 'span', [ 'id' => 'items-count' ], '0 items' );
		$html .= Html::closeElement( 'div' );
		$html .= Html::element( 'div', [ 'id' => 'items-list' ], '' );
		$html .= Html::closeElement( 'div' );

		// Review modal
		$html .= $this->getReviewModal();

		// Add Label modal
		$html .= $this->getAddLabelModal();

		$out->addHTML( $html );
	}

	private function getReviewModal() {
		$modal = '';
		$modal .= Html::openElement( 'div', [
			'id' => 'review-modal',
			'class' => 'review-modal',
			'style' => 'display: none;'
		] );
		$modal .= Html::openElement( 'div', [ 'class' => 'review-modal-content' ] );
		$modal .= Html::openElement( 'div', [ 'class' => 'review-modal-header' ] );
		$modal .= Html::element( 'h3', [], $this->msg( 'reviewdashboard-mark-reviewed' )->text() );
		$modal .= Html::element( 'button', [
			'class' => 'review-modal-close',
			'onclick' => 'ReviewDashboard.closeModal()'
		], '×' );
		$modal .= Html::closeElement( 'div' );
		$modal .= Html::element( 'p', [ 'id' => 'modal-message' ], '' );
		$modal .= Html::openElement( 'div', [ 'class' => 'review-modal-actions' ] );
		$modal .= Html::element( 'button', [
			'class' => 'btn btn-secondary',
			'onclick' => 'ReviewDashboard.closeModal()'
		], $this->msg( 'reviewdashboard-cancel' )->text() );
		$modal .= Html::element( 'button', [
			'id' => 'confirm-review-btn',
			'class' => 'btn btn-success'
		], $this->msg( 'reviewdashboard-confirm-review' )->text() );
		$modal .= Html::closeElement( 'div' );
		$modal .= Html::closeElement( 'div' );
		$modal .= Html::closeElement( 'div' );

		return $modal;
	}

	private function getAddLabelModal() {
		$modal = '';
		$modal .= Html::openElement( 'div', [
			'id' => 'add-label-modal',
			'class' => 'review-modal',
			'style' => 'display: none;'
		] );
		$modal .= Html::openElement( 'div', [ 'class' => 'review-modal-content' ] );
		$modal .= Html::openElement( 'div', [ 'class' => 'review-modal-header' ] );
		$modal .= Html::element( 'h3', [], 'Add New Label' );
		$modal .= Html::element( 'button', [
			'class' => 'review-modal-close',
			'onclick' => 'ReviewDashboard.closeAddLabelModal()'
		], '×' );
		$modal .= Html::closeElement( 'div' );

		$modal .= Html::openElement( 'div', [ 'class' => 'review-modal-body' ] );
		$modal .= Html::element( 'p', [], 'Adding label for: ' );
		$modal .= Html::element( 'strong', [ 'id' => 'modal-item-name' ], '' );

		// Language select
		$modal .= Html::openElement( 'div', [ 'class' => 'form-group' ] );
		$modal .= Html::element( 'label', [ 'for' => 'language-select' ], 'Language:' );
		$modal .= Html::openElement( 'select', [ 'id' => 'language-select', 'class' => 'form-control' ] );
		$modal .= Html::element( 'option', [ 'value' => 'en' ], '🇺🇸 English' );
		$modal .= Html::element( 'option', [ 'value' => 'es' ], '🇪🇸 Español' );
		$modal .= Html::element( 'option', [ 'value' => 'fr' ], '🇫🇷 Français' );
		$modal .= Html::element( 'option', [ 'value' => 'de' ], '🇩🇪 Deutsch' );
		$modal .= Html::element( 'option', [ 'value' => 'it' ], '🇮🇹 Italiano' );
		$modal .= Html::closeElement( 'select' );
		$modal .= Html::closeElement( 'div' );

		// Label input
		$modal .= Html::openElement( 'div', [ 'class' => 'form-group' ] );
		$modal .= Html::element( 'label', [ 'for' => 'label-input' ], 'Instrument Name:' );
		$modal .= Html::input( 'label-input', '', 'text', [
			'id' => 'label-input',
			'class' => 'form-control',
			'placeholder' => 'Enter instrument name...'
		] );
		$modal .= Html::closeElement( 'div' );
		$modal .= Html::closeElement( 'div' );

		// Footer buttons
		$modal .= Html::openElement( 'div', [ 'class' => 'review-modal-actions' ] );
		$modal .= Html::element( 'button', [
			'class' => 'btn btn-secondary',
			'onclick' => 'ReviewDashboard.closeAddLabelModal()'
		], 'Cancel' );
		$modal .= Html::element( 'button', [
			'class' => 'btn btn-primary',
			'onclick' => 'ReviewDashboard.confirmAddLabel()'
		], 'Add Label' );
		$modal .= Html::closeElement( 'div' );
		$modal .= Html::closeElement( 'div' );
		$modal .= Html::closeElement( 'div' );

		return $modal;
	}

	public function userCanExecute( User $user ) {
		return $user->isAllowed( 'reviewdashboard-view' );
	}

	protected function checkPermissions() {
		$user = $this->getUser();

		if ( !$user->isLoggedIn() ) {
			throw new UserNotLoggedIn();
		}

		if ( !$user->isAllowed( 'reviewdashboard-view' ) ) {
			throw new PermissionsError( 'reviewdashboard-view' );
		}
	}

	private function getUserPermissionLevel() {
		$user = $this->getUser();

		if ( $user->isAllowed( 'reviewdashboard-approve' ) ) {
			return 'senior'; // Can approve/mark as reviewed
		} elseif ( $user->isAllowed( 'reviewdashboard-edit' ) ) {
			return 'junior'; // Can add labels but not approve
		} else {
			return 'viewer'; // View only (shouldn't happen if permissions are set correctly)
		}
	}

	public function getDescription() {
		return $this->msg( 'reviewdashboard-special-page' );
	}
}
