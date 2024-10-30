(function ($) {
	$(document).ready(() => {
		function addExportCSVButton() {
			let buttons = '<div class="csp-export-buttons"><button type="button" class="button button-primary tips csomagpont_api_send_btn" name="csomagpont_api_send">Feladás Csomagponttal</button>';
			buttons += '<button type="button" class="button button-primary tips csp_api_label_btn" name="csp_api_label">Csomagcímke</button>';
			buttons += '<button type="button" class="button button-primary tips csp_api_mpl_sending_btn" name="csp_api_mpl_sending">Feladójegyzék</button>';
			buttons += '<a href="https://wordpress.org/support/plugin/csomagpont/reviews/#new-post" target="_blank" style="position: relative; top: 13px;">';
			buttons += 'Kérjük értékelje a Csomagpont plugint 5⭐-gal</a>';
			buttons += '</div>';

			$(buttons).insertBefore('#wpbody-content > div.wrap > ul');

		}

		function getCheckedOrders() {
			const orderIDs = [];
			$('#the-list input[id^=cb-select-]:checkbox:checked').each(function () {
				if ($(this).parent().parent().children('.group_code')
					.children('select')
					.val()) {
					orderIDs.push($(this).parent().parent().children('.group_code')
						.children('select')
						.val());
				}
			});

			return orderIDs;
		}

		function getCheckedOrderUnits() {
			const orderUnits = [];
			$('#the-list input[id^=cb-select-]:checkbox:checked').each(function () {
				if ($(this).parent().parent().children('.packaging_unit')
					.children('input')
					.val()) {
						orderUnits.push($(this).parent().parent().children('.packaging_unit')
						.children('input')
						.val());
				}
			});

			return orderUnits;
		}

		function getCheckedOrderPackageMaterialWeight() {
			const orderPackageMaterialWeight = [];
			$('#the-list input[id^=cb-select-]:checkbox:checked').each(function () {
				if ($(this).parent().parent().children('.package_material_weight')
					.children('input')
					.val()) {
						orderPackageMaterialWeight.push($(this).parent().parent().children('.package_material_weight')
						.children('input')
						.val());
				}
			});

			return orderPackageMaterialWeight;
		}

		function getCheckedOrderItemWeight() {
			const orderItemWeight = [];
			$('#the-list input[id^=cb-select-]:checkbox:checked').each(function () {
				if ($(this).parent().parent().children('.item_weight')
					.children('input')
					.val()) {
						orderItemWeight.push($(this).parent().parent().children('.item_weight')
						.children('input')
						.val());
				}
			});

			return orderItemWeight;
		}

		function getCheckedOrderPackageWeight() {
			const orderPackageWeight = [];
			$('#the-list input[id^=cb-select-]:checkbox:checked').each(function () {
				if ($(this).parent().parent().children('.package_weight')
					.children('input')
					.val()) {
						orderPackageWeight.push($(this).parent().parent().children('.package_weight')
						.children('input')
						.val());
				}
			});

			return orderPackageWeight;
		}

		function getCheckedSentOrders() {
			const orderIDs = [];
			$('#the-list input[id^=cb-select-]:checkbox:checked').each(function () {
				if ($(this).parent().parent().children('.group_code')
					.children('.csomagpont-cell')
					.data('groupid')) {
					orderIDs.push($(this).parent().parent().children('.group_code')
						.children('.csomagpont-cell')
						.data('groupid'));
				}
			});

			return orderIDs;
		}

		function getCheckedMplSentOrders() {
			const orderIDs = [];
			$('#the-list input[id^=cb-select-]:checkbox:checked').each(function () {
				if ($(this).parent().parent().children('.group_code')
					.children('.csomagpont-cell')
					.data('groupid') &&
					$(this).parent().parent().children('.group_code')
					.children('.csp-mpl-sent').length) {
					orderIDs.push($(this).parent().parent().children('.group_code')
						.children('.csomagpont-cell')
						.data('groupid'));
				}
			});

			return orderIDs;
		}

		function getPackageForChecked() {
			const packages = [];
			$('#the-list input[id^=cb-select-]:checkbox:checked').each(function () {
				if ($(this).parent().parent().children('.group_code')
					.children('.csomagpont-cell')
					.data('dsmid')) {
					const packageId = $(this).parent().parent().children('.group_code')
						.children('.csomagpont-cell')
						.data('groupid');
					const delivery = $(this).parent().parent().children('.group_code')
						.children('.csomagpont-cell')
						.data('dsmid');
					packages.push({
						packageId,
						delivery,
					});
				}
			});

			return packages;
		}

		function csomagpontSend(url) {
			let orderIDs = getCheckedOrders();
			let orderUnits = getCheckedOrderUnits();
			let orderItemWeight = getCheckedOrderItemWeight();
			let orderPackageWeight = getCheckedOrderPackageWeight();
			let orderPackageMaterialWeight = getCheckedOrderPackageMaterialWeight();

			if (orderUnits.length !== orderIDs.length) {
				stopLoading();
				alert('A rendelési egységek száma nem egyezik meg a rendelések számával! Kérjük, ellenőrizze, hogy teljesen kitöltötte-e a plugin beállításait!');
				return;
			}

			if (orderItemWeight.length !== orderIDs.length) {
				stopLoading();
				alert('A rendelési csomag súlyok száma nem egyezik meg a rendelések számával! Kérjük, ellenőrizze, hogy teljesen kitöltötte-e a plugin beállításait!');
				return;
			}
			
			if (orderPackageWeight.length !== orderIDs.length) {
				stopLoading();
				alert('A rendelési egész csomag súlyok száma nem egyezik meg a rendelések számával! Kérjük, ellenőrizze, hogy teljesen kitöltötte-e a plugin beállításait!');
				return;
			}

			for (let i = 0; i < orderIDs.length; i++) {
				orderIDs[i] += '-' + orderUnits[i];
				orderIDs[i] += '-' + orderItemWeight[i];
				orderIDs[i] += '-' + orderPackageWeight[i];
				orderIDs[i] += '-' + orderPackageMaterialWeight[i];
			}

			if (orderIDs.length > 0) {
				$('button[name=csomagpont_api_send]').text('Rendelések küldése folyamatban...');
				orderIDs = orderIDs.join();
				url += orderIDs;

				// console.log(url)
				//
				// stopLoading();
				// alert(url);
				// return;
				window.location.href = url;
			} else {
				stopLoading();
				alert('Kérjük válasszon legalább egy rendelést, amely még nincs feladva és érvényes szállítási móddal rendelkezik!');
			}
		}

		// Ajax fájlok letöltéséhez
		function cspOpenAjaxLink(url) {
			const a = document.createElement('a');
			a.style = 'display: none';
			a.target = '_blank';
			document.body.appendChild(a);
			a.href = url;
			a.click();
		}

		function startLoading() {
			$('.csp-loader').show();
		}

		function stopLoading() {
			$('.csp-loader').hide();
		}

		stopLoading();

		$(document).on('click', '.csomagpont_api_send_btn', (e) => {
			startLoading();
			const currentURL = document.URL;
			const apiURL = `${currentURL.split('edit.php')[0]}edit.php?post_type=shop_order&csomagpont_api_send=`;

			csomagpontSend(apiURL);
		});

		$(document).on('click', '.csp_api_label_btn', (e) => {
			startLoading();
			const packages = getPackageForChecked();
			if (packages.length < 1) {
				stopLoading();
				alert('Egy kiválasztott rendelés sincs elküldve');
				return;
			}
			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'csp_api_label',
					packages,
				},
				success(url) {
					stopLoading();
					cspOpenAjaxLink(url);
				},
				error(error) {
					stopLoading();
					alert(error);
				},
			});
		});

		$(document).on('click', '.csp_api_mpl_sending_btn', (e) => {
			startLoading();
			let groupIDs = getCheckedMplSentOrders();
			if (groupIDs.length < 1) {
				alert('Egy kiválasztott rendelés sincs MPL futárszolgálattal elküldve');
				return;
			}
			groupIDs = groupIDs.join();
			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'csp_api_mpl_sending',
					group_ids: groupIDs,
				},
				success(url) {
					stopLoading();
					cspOpenAjaxLink(url);
				},
				error(error) {
					stopLoading();
					alert(error);
				},
			});
		});


		addExportCSVButton();
	});
}(jQuery));