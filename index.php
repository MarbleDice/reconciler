<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Reconciler</title>
		<meta charset="utf-8" />
		<link rel="stylesheet" href="default.css" />
		<script src="https://code.jquery.com/jquery-3.5.0.min.js"></script>
		<script src="reconciler.js"></script>
	</head>
	<body>
<?php

const MAX_LEFT = 3;
const MAX_RIGHT = 3;

class LineItem {
	public $id = 1;
	public $amount;
	public $date;
	public $memo = '';

	function __construct($rawAmount) {
		// Strip commas and convert to cents
		$this->amount = (int) round(100 * str_replace(',', '', $rawAmount));
	}

	function __toString() {
		return moneyFormat($this->amount);
	}
}

class Solution {
	public $id = 1;
	public $lefts = array();
	public $rights = array();
	public $isUnique = true;

	function __construct($lefts, $rights) {
		$this->lefts = $lefts;
		$this->rights = $rights;
	}

	function count() {
		return count($this->lefts) + count($this->rights);
	}

	function countIntersection($solution) {
		return count(array_intersect($this->lefts, $solution->lefts))
				+ count(array_intersect($this->rights, $solution->rights));
	}

	function getLeftIds() {
		return implode(' ', array_map(function ($lineItem) {
			return $lineItem->id;
		}, $this->lefts));
	}

	function getRightIds() {
		return implode(' ', array_map(function ($lineItem) {
			return $lineItem->id;
		}, $this->rights));
	}

	function __toString() {
		return sprintf("%s = %s", implode(" + ", $this->lefts), implode(" + ", $this->rights));
	}
}

function moneyFormat($number) {
	return sprintf("%.2f", $number / 100.0);
}

function extractNumbers($text, $mode, $amountColumn, $dateColumn, $memoColumn) {
	// Extract line items
	$lineItems = array();
	if ($mode == 'input-all') {
		$lineItems = extractAllNumbers($text);
	} else if ($mode == 'input-tsv') {
		$lineItems = extractNumbersFromTsv($text, $amountColumn, $dateColumn, $memoColumn);
	}

	// Filter the 0-amount line items and re-index
	$lineItems = array_values(array_filter($lineItems, function($li) {
		return $li->amount != 0;
	}));

	// Apply IDs
	foreach ($lineItems as $i => $lineItem) {
		$lineItem->id = $i + 1;
	}

	return $lineItems;
}

function extractAllNumbers($text) {
	preg_match_all('/-?\d+(\.\d+)?/', str_replace(',', '', $text), $matches);
	return array_map(function ($raw) {
		return new LineItem($raw);
	}, $matches[0]);
}

function extractNumbersFromTsv($text, $amountColumn, $dateColumn, $memoColumn) {
	$lineItems = array();
	$lines = preg_split('/[\n\r]+/', $text);
	foreach ($lines as $line) {
		$match = preg_split('/\t/', $line);
		if (count($match) >= $amountColumn) {
			$lineItem = new LineItem($match[$amountColumn - 1]);
			$lineItem->date = date('Y-m-d', strtotime($match[$dateColumn - 1]));
			$lineItem->memo = $match[$memoColumn - 1];

			array_push($lineItems, $lineItem);
		}
	}
	return $lineItems;
}

function lastIndex($arr) {
	return count($arr) - 1;
}

function add($arr, $value) {
	array_push($arr, $value);
	return $arr;
}

function rightSlice($arr, $index) {
	return array_slice($arr, $index + 1, count($arr));
}

function sumLineItems($lineItems) {
	return array_reduce($lineItems, function($reduction, $lineItem) {
		return $reduction + $lineItem->amount;
	});
}

// Uses a breadth-first search and prunes branches when a solution is found or not possible
function checkLeft($chosenLefts, $chosenRights, $otherLefts, $otherRights, &$solutions) {
	// Check all the rights for this combination of lefts
	if (count($chosenLefts) > 0) {
		checkRight($chosenLefts, $chosenRights, $otherLefts, $otherRights, $solutions);
	}

	// Pick one more left and try those
	for ($i = 0; $i < count($otherLefts) && count($chosenLefts) < MAX_LEFT; $i++) {
		checkLeft(add($chosenLefts, $otherLefts[$i]), $chosenRights,
				rightSlice($otherLefts, $i), $otherRights, $solutions);
	}
}

function checkRight($chosenLefts, $chosenRights, $otherLefts, $otherRights, &$solutions) {
	// Check if this is a solution
	$leftSum = sumLineItems($chosenLefts);
	$rightSum = sumLineItems($chosenRights);
	// printf("<!-- Checking %s %s %s %s -->\n", new Solution($chosenLefts, $chosenRights), $leftSum, $rightSum, $leftSum == $rightSum);
	if (count($chosenLefts) > 0 && count($chosenRights) > 0
			&& $leftSum == $rightSum) {
		addSolution($solutions, new Solution($chosenLefts, $chosenRights));
	}

	// We can abort if we found a solution or went too far
	if ($rightSum >= $leftSum) {
		return;
	}

	// Pick one more right and try those
	for ($i = 0; $i < count($otherRights) && count($chosenRights) < MAX_RIGHT; $i++) {
		checkRight($chosenLefts, add($chosenRights, $otherRights[$i]),
				$otherLefts, rightSlice($otherRights, $i), $solutions);
	}
}

function addSolution(&$solutions, $newSolution) {
	printf("<!--     Adding %s -->\n", $newSolution);
	foreach ($solutions as $s => $solution) {
		if ($newSolution->countIntersection($solution) == $solution->count()) {
			// An existing solution is a subset of the new solution, so abort
			printf("<!--     Skipping due to %s -->\n", $solution);
			return;
		}
	}
	$solution->id += count($solutions);
	array_push($solutions, $newSolution);
}

function checkUniqueness($solutions) {
	foreach ($solutions as $s1 => $solution) {
		foreach (rightSlice($solutions, $s1) as $s2 => $otherSolution) {
			if (count(array_intersect($solution->lefts, $otherSolution->lefts)) > 0
					|| count(array_intersect($solution->rights, $otherSolution->rights)) > 0) {
				$solution->isUnique = false;
				$otherSolution->isUnique = false;
			}
		}
	}
}

function filterLeft($solutions, $number) {
	return array_filter($solutions, function($solution) use ($number) {
		return in_array($number, $solution->lefts, true);
	});
}

function filterRight($solutions, $number) {
	return array_filter($solutions, function($solution) use ($number) {
		return in_array($number, $solution->rights, true);
	});
}

function printTable($form, $lefts, $rights, $solutions, $isRight) {
	$label = $isRight ? $form['right-label'] : $form['left-label'];
	$cssClass = $isRight ? 'right' : 'left';
	$filter = $isRight ? 'filterRight' : 'filterLeft';
	$lineItems = $isRight ? $rights : $lefts;
	$total = $isRight ? sumLineItems($rights) : sumLineItems($lefts);
	$otherTotal = $isRight ? sumLineItems($lefts) : sumLineItems($rights);

	foreach ($lineItems as $n => $lineItem) {
		// Table header
		if ($n == 0) {
			printf("<table class=\"data $cssClass\"><thead>\n");
			printf("<tr><th colspan=\"2\">%s</th></tr>\n", $label);
			printf("<th>Amount</th>");
			printf("<th>Possible Matches</th>");
			printf("</tr></thead>\n");
			printf("<tbody>\n");
		}

		printf("<tr %s=\"%d\">\n", $isRight ? 'data-right-id' : 'data-left-id', $lineItem->id);

		// Number
		printf("<td class=\"label\">\n");
		printf("%s\n", $lineItem);
		printf("<span class=\"action\" onclick=\"javascript:%s(%d)\">&#x2716;</span>\n", $isRight ? 'removeRight' : 'removeLeft', $lineItem->id);
		printf("</td>\n");

		// Solutions
		$filteredSolutions = array_values($filter($solutions, $lineItem));
		printf("<td>\n");
		foreach ($filteredSolutions as $s => $solution) {
			if ($s == 0) printf("<ul>\n");
			printf("<li class=\"%s\" data-solution-id=\"%d\" data-left-ids=\"%s\" data-right-ids=\"%s\">",
					$solution->isUnique ? 'unique' : 'non-unique', $solution->id, $solution->getLeftIds(), $solution->getRightIds());
			printf("<span onclick=\"javascript:openModal(%d)\">%s</span>\n", $solution->id, $solution);
			printf("<span class=\"action\" onclick=\"javascript:confirmSolution(%d)\">&#x2713;</span>\n", $solution->id);
			printf("<span class=\"action\" onclick=\"javascript:removeSolution(%d)\">&#x2716;</span>\n", $solution->id);
			printf("</li>\n");
			if ($s == lastIndex($filteredSolutions)) printf("</ul>\n");
		}
		printf("</td>\n");

		printf("</tr>\n");

		// Table footer
		if ($n == lastIndex($lineItems)) {
			printf("</tbody><thead>\n");
			printf("<tr><td>Total:</td><td>%s</td></tr>\n", moneyFormat($total));
			printf("<tr><td>Difference:</td><td>%s</td></tr>\n", moneyFormat($total - $otherTotal));
			printf("</thead></table>\n");
		}
	}
}

function printModals($form, $solutions) {
	foreach ($solutions as $s => $solution) {
		printf("<div class=\"modal\" data-solution-id=\"%d\">\n", $solution->id);
		printf("<div>\n");

		printf("<table class=\"data\">\n");
		printf("<thead><tr><th colspan=\"3\">%s</th></tr></thead>\n", $form['left-label']);
		foreach ($solution->lefts as $lineItem) {
			printf("<tr><td>%s</td><td>%s</td><td class=\"text\">%s</td></tr>\n",
					$lineItem->date, moneyFormat($lineItem->amount), $lineItem->memo);
		}
		printf("<thead><tr><th colspan=\"3\">%s</th></tr></thead>\n", $form['right-label']);
		foreach ($solution->rights as $lineItem) {
			printf("<tr><td>%s</td><td>%s</td><td class=\"text\">%s</td></tr>\n",
					$lineItem->date, moneyFormat($lineItem->amount), $lineItem->memo);
		}
		printf("</table>\n");

		printf("<span class=\"action\" onclick=\"javascript:confirmSolution(%d)\">&#x2713; Accept</span>\n", $solution->id);
		printf("<span class=\"action\" onclick=\"javascript:removeSolution(%d)\">&#x2716; Delete</span>\n", $solution->id);
		printf("<span class=\"action\" onclick=\"javascript:closeModal(%d)\">Close</span>\n", $solution->id);
		printf("</div>\n");
		printf("</div>\n");
	}
}

function extractInt($name, $defaultValue) {
	return intval($_POST[$name]) > 0 ? intval($_POST[$name]) : $defaultValue;
}

// Extract input

$form['left-label'] = "DSS";
$form['left-input'] = $_POST['left-input'];
$form['left-mode'] = $_POST['left-mode'] == "input-all" ? "input-all" : "input-tsv";
$form['left-amount-col'] = extractInt('left-amount-col', 3);
$form['left-date-col'] = extractInt('left-date-col', 2);
$form['left-memo-col'] = extractInt('left-memo-col', 6);

$form['right-label'] = "Bank Activity";
$form['right-input'] = $_POST['right-input'];
$form['right-mode'] = $_POST['right-mode'] == "input-all" ? "input-all" : "input-tsv";
$form['right-amount-col'] = extractInt('right-amount-col', 3);
$form['right-date-col'] = extractInt('right-date-col', 2);
$form['right-memo-col'] = extractInt('right-memo-col', 6);

$lefts = extractNumbers($form['left-input'], $form['left-mode'],
		$form['left-amount-col'], $form['left-date-col'], $form['left-memo-col']);
$rights = extractNumbers($form['right-input'], $form['right-mode'],
		$form['right-amount-col'], $form['right-date-col'], $form['right-memo-col']);

// Find all solutions
$solutions = array();
checkLeft(array(), array(), $lefts, $rights, $solutions);
checkUniqueness($solutions);

?>
<main>
<div class="modal">
	<div>
		<h2>Hello</h2>
		<p>Some content for you</p>
	</div>
</div>
<h1>Reconciler</h1>
<p>Paste amounts from one side into <span class="left">left amounts</span> field and amounts from the other side into
<span class="right">right amounts</span> field. Press <span class="label">Reconcile</span> to see two tables for the
left and right amounts, which display every amount and every possible exact match between sides. <span class="unique">
Unique matches</span> only contain amounts that cannot be matched in any other combination, while
<span class="non-unique">ambiguous matches</span> contain amounts that appear in multiple different matches.</p>
<form method="post">
<table class="form">
	<tr>
		<td colspan="2"><span class="label"><?= $form['left-label'] ?> Data</span></td>
		<td></td>
		<td colspan="2"><span class="label"><?= $form['right-label'] ?> Data</span></td>
	</tr>
	<tr>
		<td colspan="2">
			<textarea name="left-input"><?= htmlentities($form['left-input']) ?></textarea>
		</td>
		<td>
			<ul class="textarea-actions">
				<li><span class="action" onclick="javascript:swapTextarea();">&laquo;swap&raquo;</span></li>
				<li><span class="action" onclick="javascript:clearTextarea();">&laquo;clear&raquo;</span></li>
			</ul>
		</td>
		<td colspan="2">
			<textarea name="right-input"><?= htmlentities($form['right-input']) ?></textarea>
		</td>
	</tr>
	<tr><td colspan="5"></td></tr>
	<tr>
		<td colspan="3" class="label">Input mode</td>
		<td colspan="2" class="label">Input mode</td>
	</tr>
	<tr>
		<td>
			<input type="radio" name="left-mode" id="left-input-tsv" value="input-tsv" <?=$form['left-mode'] == 'input-tsv' ? 'checked' : ''?> />
		</td>
		<td>
			<label for="left-input-tsv">Read data in TSV format</label>
		</td>
		<td></td>
		<td>
			<input type="radio" name="right-mode" id="right-input-tsv" value="input-tsv" <?=$form['right-mode'] == 'input-tsv' ? 'checked' : ''?> />
		</td>
		<td>
			<label for="right-input-tsv">Read data in TSV format</label>
		</td>
	</tr>
	<tr>
		<td></td>
		<td>Amount in column #<input type="number" name="left-amount-col" size="2" value="<?=$form['left-amount-col']?>"></input></td>
		<td></td>
		<td></td>
		<td>Amount in column #<input type="number" name="right-amount-col" size="2" value="<?=$form['right-amount-col']?>"></input></td>
	</tr>
	<tr>
		<td></td>
		<td>Date in column #<input type="number" name="left-date-col" size="2" value="<?=$form['left-date-col']?>"></input></td>
		<td></td>
		<td></td>
		<td>Date in column #<input type="number" name="right-date-col" size="2" value="<?=$form['right-date-col']?>"></input></td>
	</tr>
	<tr>
		<td></td>
		<td>Memo in column #<input type="number" name="left-memo-col" size="2" value="<?=$form['left-memo-col']?>"></input></td>
		<td></td>
		<td></td>
		<td>Memo in column #<input type="number" name="right-memo-col" size="2" value="<?=$form['right-memo-col']?>"></input></td>
	</tr>
	<tr>
		<td>
			<input type="radio" name="left-mode" id="left-input-all" value="input-all" <?=$form['left-mode'] == 'input-all' ? 'checked' : ''?> />
		</td>
		<td>
			<label for="left-input-all">Read all numbers</label>
		</td>
		<td></td>
		<td>
			<input type="radio" name="right-mode" id="right-input-all" value="input-all" <?=$form['right-mode'] == 'input-all' ? 'checked' : ''?> />
		</td>
		<td>
			<label for="right-input-all">Read all numbers</label>
		</td>
	</tr>
	<tr><td colspan="2"></td></tr>
	<tr>
		<td colspan="2">
			<input type="submit" value="Reconcile" />
		</td>
	</tr>
</table>
</form>
<?php printTable($form, $lefts, $rights, $solutions, false); ?>
<?php printTable($form, $lefts, $rights, $solutions, true); ?>
<?php printModals($form, $solutions); ?>
</main>
</body>
</html>
