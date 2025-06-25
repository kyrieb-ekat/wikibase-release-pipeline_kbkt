<?php

class SpecialReviewDashboard extends SpecialPage {

    public function __construct() {
        parent::__construct( 'ReviewDashboard' );
    }

    public function execute( $subPage ) {
        $this->setHeaders();
        $this->outputHeader();

        // PERMISSIONS COMMENTED OUT FOR TESTING
        // $user = $this->getUser();
        // if ( $user->isLoggedIn() && !$user->isAllowed( 'reviewdashboard-access' ) ) {
        //     throw new PermissionsError( 'reviewdashboard-access' );
        // }

        $out = $this->getOutput();
        $out->addModules( 'ext.reviewdashboard' );

        // PERMISSION LEVEL COMMENTED OUT - Default to 'senior' for testing
        // $out->addJsConfigVars( 'reviewDashboardPermissions', $this->getUserPermissionLevel() );
        $out->addJsConfigVars( 'reviewDashboardPermissions', 'senior' );

        $this->showDashboard( $out );
    }

    private function showDashboard( $out ) {
        $html = '';

        // Header
        $html .= '<div class="review-dashboard-header">';
        $html .= '<h1>Items Needing Review</h1>';
        $html .= '<p>Review and approve items with musical instrument classification</p>';
        $html .= '</div>';

        // Configuration section
        $html .= '<div class="review-dashboard-config">';
        $html .= '<h2>Configuration</h2>';

        // SPARQL endpoint input
        $html .= '<div class="form-group">';
        $html .= '<label for="sparql-endpoint">SPARQL Endpoint URL:</label>';
        $html .= '<input type="url" id="sparql-endpoint" class="form-control" placeholder="http://wikibase.local/extensions/ReviewDashboard/api/sparql-proxy.php" />';
        $html .= '</div>';

        // Wikibase URL input
        $html .= '<div class="form-group">';
        $html .= '<label for="wikibase-url">Wikibase Base URL:</label>';
        $html .= '<input type="url" id="wikibase-url" class="form-control" placeholder="http://wikibase.local" />';
        $html .= '</div>';

        // Load button
        $html .= '<button id="load-items-btn" class="btn btn-primary">Load Items Needing Review</button>';
        $html .= '</div>';

        // Message area
        $html .= '<div id="review-messages"></div>';

        // Results area
        $html .= '<div id="review-results" style="display: none;">';
        $html .= '<div class="review-results-header">';
        $html .= '<h2>Items Requiring Review</h2>';
        $html .= '<span id="items-count">0 items</span>';
        $html .= '</div>';
        $html .= '<div id="items-list"></div>';
        $html .= '</div>';

        // Add modals
        $html .= $this->getReviewModal();
        $html .= $this->getAddLabelModal();

        // Add basic CSS
        $html .= '<style>
            .review-dashboard-header { margin-bottom: 20px; }
            .review-dashboard-config { background: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            .form-group { margin-bottom: 15px; }
            .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
            .form-control { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
            .btn { padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
            .btn-primary { background: #0073aa; color: white; }
            .btn-primary:hover { background: #005a87; }
            .btn-success { background: #46b450; color: white; }
            .btn-success:hover { background: #37a000; }
            .btn-small { padding: 5px 10px; font-size: 12px; }
            .btn-secondary { background: #6c757d; color: white; }
            .review-results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
            .review-item { border: 1px solid #ddd; margin-bottom: 10px; padding: 15px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
            .item-info a { text-decoration: none; font-weight: bold; margin-right: 10px; }
            .item-actions button { margin-left: 5px; }
            .message { padding: 10px; margin: 10px 0; border-radius: 3px; }
            .message-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .message-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .review-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
            .review-modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border-radius: 5px; width: 80%; max-width: 500px; }
            .review-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .review-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; }
            .review-modal-actions { margin-top: 20px; text-align: right; }
            .review-modal-actions button { margin-left: 10px; }
        </style>';

        $out->addHTML( $html );
    }

    private function getReviewModal() {
        return '<div id="review-modal" class="review-modal">
            <div class="review-modal-content">
                <div class="review-modal-header">
                    <h3>Mark as Reviewed</h3>
                    <button class="review-modal-close" onclick="ReviewDashboard.closeModal()">×</button>
                </div>
                <p id="modal-message"></p>
                <div class="review-modal-actions">
                    <button class="btn btn-secondary" onclick="ReviewDashboard.closeModal()">Cancel</button>
                    <button id="confirm-review-btn" class="btn btn-success">Mark as Reviewed</button>
                </div>
            </div>
        </div>';
    }

    private function getAddLabelModal() {
        return '<div id="add-label-modal" class="review-modal">
            <div class="review-modal-content">
                <div class="review-modal-header">
                    <h3>Add New Label</h3>
                    <button class="review-modal-close" onclick="ReviewDashboard.closeAddLabelModal()">×</button>
                </div>
                <div class="review-modal-body">
                    <p>Adding label for: <strong id="modal-item-name"></strong></p>
                    <div class="form-group">
                        <label for="language-select">Language:</label>
                        <select id="language-select" class="form-control">
                            <option value="en">🇺🇸 English</option>
                            <option value="es">🇪🇸 Español</option>
                            <option value="fr">🇫🇷 Français</option>
                            <option value="de">🇩🇪 Deutsch</option>
                            <option value="it">🇮🇹 Italiano</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="label-input">Instrument Name:</label>
                        <input type="text" id="label-input" class="form-control" placeholder="Enter instrument name..." />
                    </div>
                </div>
                <div class="review-modal-actions">
                    <button class="btn btn-secondary" onclick="ReviewDashboard.closeAddLabelModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="ReviewDashboard.confirmAddLabel()">Add Label</button>
                </div>
            </div>
        </div>';
    }

    // PERMISSION METHOD COMMENTED OUT FOR TESTING
    // private function getUserPermissionLevel() {
    //     $user = $this->getUser();
    //     if ( !$user->isLoggedIn() ) {
    //         return 'viewer';
    //     }
    //     if ( $user->isAllowed( 'reviewdashboard-admin' ) ) {
    //         return 'senior';
    //     } elseif ( $user->isAllowed( 'reviewdashboard-senior' ) ) {
    //         return 'senior';
    //     } elseif ( $user->isAllowed( 'reviewdashboard-access' ) ) {
    //         return 'junior';
    //     } else {
    //         return 'viewer';
    //     }
    // }

    public function getDescription() {
        return $this->msg( 'reviewdashboard-special-page' );
    }
}
