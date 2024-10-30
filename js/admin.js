(function ($) {
	$.ajaxSetup({
		cache: false
	});
	$('body').on('keyup', '.csomagpont-modal', function (e) {
		if (e.key === "Escape") {
			$(this).remove();
		}
	})
	$(document).on('keydown', function (event) {
		if (event.key == "Escape") {
			$('body').css('overflow', 'auto');
			$('.csomagpont-modal').remove();
		}
	});
	$(document).on('click', '#csomagpont-save-api', function () {
		var apiKey = $('#csomagpont-api-key').val();


		if (apiKey.length > 0) {
			$.ajax({
				type: 'post',
				url: ajaxurl,
				data: {
					action: 'save_api_key',
					api_key: apiKey
				},
				success: function (response) {
					location.reload();
				}
			});
		}
	});

	function startLoading() {
		$('.csp-loader').show();
	}

	function stopLoading() {
		$('.csp-loader').hide();
	}



	$(document).on('click', '.csomagpont-cell', function () {
		var group_id = $(this).data('groupid');
		label_download(group_id)

	});
	$(document).on('click', '#csomagpont-close', function () {
		$('.csomagpont-modal').remove();
		$('body').css('overflow', 'auto');
	})
	$(document).on('click', '.csomagpont_api_custom_send_btn', function () {
		var checked = [];
		$('#the-list input[id^=cb-select-]:checkbox:checked').each(function () {
			checked.push({
				"order": $(this).parent().parent().children('.group_code').children('select').attr('name'),
				"option": $(this).parent().parent().children('.group_code').children('select').val()
			});
		})
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'api_custom_send',
				group_ids: checked
			},
			success: function (response) {},
			error: function (errorThrown) {
				console.warn(errorThrown);
			}
		});

	});

	function packageLog(group_id) {
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'view_package_log',
				group_id: group_id
			},
			success: function (response) {
				$('#package_log').html(response);
			},
			error: function (errorThrown) {
				console.warn(errorThrown);
			}
		});
	}

	function view_signature(group_id) {
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'view_signature',
				group_id: group_id
			},
			success: function (response) {
				var resp = JSON.parse(response);
				if (resp['img'] != '') {
					$('#csomagpont-signature').html(resp['img']);
				} else {
					$('#csomagpont-signature').remove();
				}

				packageLog(group_id);
			},
			error: function (errorThrown) {
				console.warn(errorThrown);
			}
		});
	}

	function mpl_sending_download(group_id) {
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'mpl_sending_download',
				group_id: group_id
			},
			success: function (response) {
				$('#csomagpont-mpl-sending').html(response);
			},
			error: function (errorThrown) {
				console.warn(errorThrown);
			}
		});
	}

	function label_download(group_id) {
		startLoading();
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'label_download',
				group_id: group_id
			},
			success: function (response) {
				$('body').css('overflow', 'hidden');
				var modal = '<div class="csomagpont-modal container">';
				modal += '<div class="content">';
				modal += '<h2><span></span>' + group_id + ' csomagcsoport adatai</h2>';
				modal += '<hr>';
				modal += '<div id="package_info"></div>';
				modal += '<div id="package_log" style="text-align:center;">';
				modal += '</div>';
				modal += '<hr>';
				modal += '<div id="csomagpont-footer">';
				modal += '<div id="csomagpont-label">' + response + '</div>';
				modal += '<div id="csomagpont-mpl-sending"></div>';
				modal += '<div id="csomagpont-signature"></div>';
				modal += '<div id="csomagpont-close">×</div>';
				modal += '</div>';
				modal += '</div>';
				modal += '</div>';
				$('body').append(modal);
				$('.csomagpont-modal').addClass('show');
				stopLoading();
				// packageView(group_id);
				view_signature(group_id);
				mpl_sending_download(group_id);
			},
			error: function (errorThrown) {
				stopLoading();
				console.warn(errorThrown);
			}
		});
	}

	function packageView(group_id) {
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'package_details',
				group_id: group_id
			},
			success: function (response) {
				$('#package_info').html(response);
			},
			error: function (errorThrown) {
				console.warn(errorThrown);
			}
		});
	}
})(jQuery)