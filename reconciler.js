function clearTextarea() {
	$('textarea').val('');
}

function swapTextarea() {
	var textareas = $('textarea');
	var rightText = textareas.eq(1).val();
	textareas.eq(1).val(textareas.eq(0).val());
	textareas.eq(0).val(rightText);
}

function removeLeft(leftId) {
	$('*[data-left-id=' + leftId + ']').remove();
	$('*[data-left-ids~=' + leftId + ']').remove();
	updateUniqueness();
}

function removeRight(rightId) {
	$('*[data-right-id=' + rightId + ']').remove();
	$('*[data-right-ids~=' + rightId + ']').remove();
	updateUniqueness();
}

function removeSolution(solutionId) {
	$('*[data-solution-id=' + solutionId + ']').remove();
	updateUniqueness();
}

function confirmSolution(solutionId) {
	var solution = $('*[data-solution-id=' + solutionId + ']').first();

	// Get all line items involved
	var leftIds = solution.attr('data-left-ids').split(' ');
	var rightIds = solution.attr('data-right-ids').split(' ');

	// Delete all line items involved
	leftIds.forEach(removeLeft);
	rightIds.forEach(removeRight);

	// Delete all other solutions that use those line items
	leftIds.forEach(function (id) {
		$('*[data-left-ids~=' + id + ']').remove();
	});
	rightIds.forEach(function (id) {
		$('*[data-right-ids~=' + id + ']').remove();
	});

	removeSolution(solutionId);
}

function updateUniqueness() {
	$('li.non-unique').filter(function (index) {
		var leftIds = $(this).attr('data-left-ids').split(' ');
		var rightIds = $(this).attr('data-right-ids').split(' ');
		// For uniqueness, total elements should be twice the number of combined IDs
		// Each left/right has a solution and a control
		var total = 2 * (leftIds.length + rightIds.length);

		// console.log("Checking " + leftIds + " " + rightIds + " total " + total);

		for (let i in leftIds) {
			if ($('*[data-left-ids~=' + leftIds[i] + ']').length != total) {
				return false;
			}
		}

		for (let i in rightIds) {
			if ($('*[data-right-ids~=' + rightIds[i] + ']').length != total) {
				return false;
			}
		}

		return true;
	}).removeClass('non-unique').addClass('unique');
}

function openModal(id) {
	$('.modal[data-solution-id=' + id + ']').addClass('active');
}

function closeModal(id) {
	$('.modal[data-solution-id=' + id + ']').removeClass('active');
}
