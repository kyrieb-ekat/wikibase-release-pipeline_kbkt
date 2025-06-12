( function () {
	'use strict';

	window.ReviewDashboard = {
		currentItems: [],
		selectedItemId: null,

		init: function () {
			// Set default URLs
			document.getElementById('sparql-endpoint').value = 'https://wikibase.local/extensions/ReviewDashboard/api/sparql-proxy.php';
			document.getElementById('wikibase-url').value = 'https://wikibase.local';

			// Bind events
			document.getElementById('load-items-btn').addEventListener('click', this.loadItems.bind(this));
			document.getElementById('confirm-review-btn').addEventListener('click', this.confirmReview.bind(this));

			// Close modal when clicking outside
			document.getElementById('review-modal').addEventListener('click', function(e) {
				if (e.target.id === 'review-modal') {
					ReviewDashboard.closeModal();
				}
			});

			// Close add label modal when clicking outside
			document.getElementById('add-label-modal').addEventListener('click', function(e) {
				if (e.target.id === 'add-label-modal') {
					ReviewDashboard.closeAddLabelModal();
				}
			});
		},

		loadItems: function () {
			const sparqlEndpoint = document.getElementById('sparql-endpoint').value;
			const loadButton = document.getElementById('load-items-btn');
			const messageDiv = document.getElementById('review-messages');

			if (!sparqlEndpoint) {
				this.showMessage(mw.msg('reviewdashboard-error-no-endpoint'), 'error');
				return;
			}

			loadButton.disabled = true;
			loadButton.textContent = mw.msg('reviewdashboard-loading');
			messageDiv.innerHTML = '';

			const sparqlQuery = `
				PREFIX wdt: <https://wikibase.local/prop/direct/>
				PREFIX wikibase: <http://wikiba.se/ontology#>
				PREFIX bd: <http://www.bigdata.com/rdf#>

				SELECT ?item ?itemLabel WHERE {
					?item wdt:P2 "Musical Instrument" .
					SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en" }
				}
				ORDER BY ?itemLabel
			`;

			const encodedQuery = encodeURIComponent(sparqlQuery);
			const url = `${sparqlEndpoint}?query=${encodedQuery}&format=json`;

			fetch(url, {
				headers: {
					'Accept': 'application/sparql-results+json'
				}
			})
			.then(response => {
				if (!response.ok) {
					throw new Error(`HTTP ${response.status}: ${response.statusText}`);
				}
				return response.json();
			})
			.then(data => {
				this.currentItems = data.results.bindings;
				this.displayItems(this.currentItems);
				this.showMessage(`Found ${this.currentItems.length} items needing review`, 'success');
			})
			.catch(error => {
				console.error('Error fetching data:', error);
				this.showMessage(`Error loading items: ${error.message}`, 'error');
			})
			.finally(() => {
				loadButton.disabled = false;
				loadButton.textContent = mw.msg('reviewdashboard-load-items');
			});
		},

		displayItems: function (items) {
    		  const resultsSection = document.getElementById('review-results');
    		  const itemsList = document.getElementById('items-list');
    		  const itemsCount = document.getElementById('items-count');
    		  const wikibaseUrl = document.getElementById('wikibase-url').value;
    		  const userLevel = mw.config.get('reviewDashboardPermissions');

    		  itemsCount.textContent = `${items.length} item${items.length !== 1 ? 's' : ''}`;

    		  if (items.length === 0) {
        	    itemsList.innerHTML = '<div class="no-items">No items found needing review</div>';
    		  } else {
        	    itemsList.innerHTML = items.map(item => {
            	      const itemId = item.item.value.split('/').pop();
            	      const itemLabel = item.itemLabel ? item.itemLabel.value : 'No label';
            	      const itemUrl = `${wikibaseUrl}/wiki/Item:${itemId}`;

	            // Build buttons based on permission level
        	      let actionButtons = `<button onclick="window.open('${itemUrl}', '_blank')" class="btn btn-small">View</button>`;

            	      if (userLevel === 'senior') {
                        // Senior reviewers get all buttons
                        actionButtons += `
                    	  <button onclick="window.open('${itemUrl}?action=edit', '_blank')" class="btn btn-small">Edit</button>
                    	  <button onclick="ReviewDashboard.showAddLabelModal('${itemId}', '${itemLabel}')" class="btn btn-primary btn-small">Add Label</button>
                    	  <button onclick="ReviewDashboard.showReviewModal('${itemId}')" class="btn btn-success btn-small">Mark Reviewed</button>`;
            	      } else if (userLevel === 'junior') {
                	// Junior reviewers can add labels but not mark as reviewed
                	actionButtons += `<button onclick="ReviewDashboard.showAddLabelModal('${itemId}', '${itemLabel}')" class="btn btn-primary btn-small">Add Label</button>`;
            	      }
            		// Viewers only get the View button (already added above)

            	      return `
                	<div class="review-item">
                    	  <div class="item-info">
                            <a href="${itemUrl}" target="_blank" class="item-id">${itemId}</a>
                            <span class="item-label">${itemLabel}</span>
                          </div>
                        <div class="item-actions">
                          ${actionButtons}
                        </div>
                      </div>
            	    `;
        	  }).join('');
    		}

    resultsSection.style.display = 'block';
},
		showMessage: function (message, type) {
			const messageDiv = document.getElementById('review-messages');
			messageDiv.innerHTML = `<div class="message message-${type}">${message}</div>`;

			if (type === 'success') {
				setTimeout(() => {
					messageDiv.innerHTML = '';
				}, 5000);
			}
		},

		showReviewModal: function (itemId) {
			this.selectedItemId = itemId;
			document.getElementById('modal-message').textContent =
				`Are you sure you want to mark ${itemId} as reviewed?`;
			document.getElementById('review-modal').style.display = 'block';
		},

		closeModal: function () {
			document.getElementById('review-modal').style.display = 'none';
			this.selectedItemId = null;
		},

		confirmReview: function () {
			if (!this.selectedItemId) return;

			// Demo: In a real implementation, this would call the Wikibase API
			this.showMessage(`Demo: ${this.selectedItemId} would be updated to "reviewed" status (Q6)`, 'success');

			// Remove item from current list
			this.currentItems = this.currentItems.filter(item =>
				!item.item.value.includes(this.selectedItemId)
			);
			this.displayItems(this.currentItems);

			this.closeModal();
		},

		showAddLabelModal: function (itemId, itemLabel) {
			this.selectedItemId = itemId;
			document.getElementById('modal-item-name').textContent = `${itemId} (${itemLabel})`;
			document.getElementById('label-input').value = '';
			document.getElementById('language-select').value = 'en';
			document.getElementById('add-label-modal').style.display = 'block';
		},

		closeAddLabelModal: function () {
			document.getElementById('add-label-modal').style.display = 'none';
			this.selectedItemId = null;
		},

		confirmAddLabel: function () {
			const language = document.getElementById('language-select').value;
			const labelText = document.getElementById('label-input').value.trim();

			if (!labelText) {
				this.showMessage('Please enter a label', 'error');
				return;
			}

			const addButton = document.querySelector('#add-label-modal .btn-primary');
			addButton.disabled = true;
			addButton.textContent = 'Adding...';

			this.performAddLabel(this.selectedItemId, language, labelText)
				.then(result => {
					if (result.success) {
						this.showMessage(result.successMessage, 'success');
						this.closeAddLabelModal();
						this.loadItems(); // Refresh the list
					} else if (result.cancelled) {
						// User cancelled the alias confirmation
						return;
					} else {
						this.showMessage(`Error: ${result.error.info}`, 'error');
					}
				})
				.catch(error => {
					this.showMessage(`Error adding label: ${error.message}`, 'error');
				})
				.finally(() => {
					addButton.disabled = false;
					addButton.textContent = 'Add Label';
				});
		},

		performAddLabel: async function (itemId, language, labelText) {
			// Get edit token
			const tokenResponse = await fetch('http://localhost:8080/w/api.php?action=query&meta=tokens&format=json');
			const tokenData = await tokenResponse.json();
			const token = tokenData.query.tokens.csrftoken;

			// First, check if the item already has a label in this language
			const checkResponse = await fetch(`http://localhost:8080/w/api.php?action=wbgetentities&ids=${itemId}&format=json`);
			const checkData = await checkResponse.json();

			const item = checkData.entities[itemId];
			const hasExistingLabel = item.labels && item.labels[language];

			let actionType, successMessage;

			if (hasExistingLabel) {
				// Show confirmation that this will be added as an alias
				const existingLabel = item.labels[language].value;
				const confirmed = confirm(`This item already has a ${language} label: "${existingLabel}"\n\nYour entry "${labelText}" will be added as an alias instead. Continue?`);

				if (!confirmed) {
					return { success: false, cancelled: true };
				}

				// Add as alias
				const response = await fetch('http://localhost:8080/w/api.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({
						action: 'wbsetaliases',
						id: itemId,
						language: language,
						add: labelText,
						token: token,
						format: 'json'
					})
				});

				actionType = 'alias';
				successMessage = `Added "${labelText}" as ${language} alias to ${itemId} (existing label: "${existingLabel}")`;
				return { ...(await response.json()), actionType, successMessage };

			} else {
				// Add as label (no existing label)
				const response = await fetch('http://localhost:8080/w/api.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({
						action: 'wbsetlabel',
						id: itemId,
						language: language,
						value: labelText,
						token: token,
						format: 'json'
					})
				});

				actionType = 'label';
				successMessage = `Added "${labelText}" as ${language} label to ${itemId}`;
				return { ...(await response.json()), actionType, successMessage };
			}
		}
	};

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', ReviewDashboard.init.bind(ReviewDashboard));
	} else {
		ReviewDashboard.init();
	}
}());
