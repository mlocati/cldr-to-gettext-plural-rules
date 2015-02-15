/* jshint unused:vars, undef:true, browser:true, jquery:true */
(function() {
'use strict';

function TableSorter(table, sortableCells, sortedColumn, sorterOrder)
{
	var me = this;
	me.$table = $(table);
	me.$tbody = me.$table.find('>tbody');
	me.$table.find('>thead>tr>th').each(function(index) {
		if($.inArray(index, sortableCells) >= 0) {
			var $th = $(this),
				$a = $('<a href="javascript:void(0)" />')
					.html($th.html())
					.on('click', function() {
						me.sort(index);
					})
			;
			$th.empty().append($a);
		}
		return;
	});
}
TableSorter.prototype = {
	sort: function(index) {
		var me = this, data = [];
		me.$tbody.find('>tr').each(function() {
			var $row = $(this);
			data.push({$row: $row, key: $(this.cells[index]).text().toLowerCase()});
		});
		var tmp, touched = false;
		for (var i = 0; i < data.length - 1; i++) {
			for (var j = i + 1; j < data.length - 1; j++) {
				if(data[i].key > data[j].key) {
					tmp = data[i];
					data[i] = data[j];
					data[j] = tmp;
					touched = true;
				}
			}
		}
		if(touched) {
			me.$tbody.empty();
			$.each(data, function() {
				me.$tbody.append(this.$row);
			});
		}
	}
};

$(document).ready(function() {
	$('table').each(function() {
		new TableSorter(this, [0, 1]);
	});
});

})();