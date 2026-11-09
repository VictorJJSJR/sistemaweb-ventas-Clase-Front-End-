(() => {
	const fieldSelect = document.getElementById('search-field');
	const searchInput = document.getElementById('search-value');

	const fieldOptions = {
		todos: {
			type: 'search',
			inputMode: 'search',
			placeholder: 'Ej. 2020, 6790 o 10'
		},
		id: {
			type: 'number',
			inputMode: 'numeric',
			placeholder: 'Ej. 3',
			step: '1',
			min: '0',
			max: '2147483647'
		},
		num_ventas: {
			type: 'number',
			inputMode: 'numeric',
			placeholder: 'Ej. 20',
			step: '1',
			min: '-2147483648',
			max: '2147483647'
		},
		total_ventas: {
			type: 'number',
			inputMode: 'decimal',
			placeholder: 'Ej. 12790.00',
			step: '0.01'
		},
		fecha: {
			type: 'date',
			inputMode: 'numeric',
			placeholder: 'AAAA-MM-DD'
		}
	};

	if (fieldSelect && searchInput) {
		fieldSelect.addEventListener('change', () => {
			const option = fieldOptions[fieldSelect.value] || fieldOptions.todos;
			const currentValue = searchInput.value;
			searchInput.type = option.type;
			searchInput.inputMode = option.inputMode;
			searchInput.placeholder = option.placeholder;
			searchInput.value = currentValue;
			searchInput.removeAttribute('step');
			searchInput.removeAttribute('min');
			searchInput.removeAttribute('max');

			if (option.step) {
				searchInput.step = option.step;
			}
			if (option.min !== undefined) {
				searchInput.min = option.min;
			}
			if (option.max !== undefined) {
				searchInput.max = option.max;
			}

			searchInput.focus();
		});
	}

	document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
		form.addEventListener('submit', (event) => {
			const idInput = form.querySelector('[name="idreg"]');
			const recordId = idInput ? idInput.value : '';
			const message = (form.dataset.confirmMessage || '¿Confirmas esta acción?').replace('{id}', recordId);
			if (!window.confirm(message)) {
				event.preventDefault();
			}
		});
	});

	function compareDecimals(first, second) {
		const parseDecimal = (value) => {
			const match = value.match(/^(-?)(\d+)(?:\.(\d+))?$/);
			if (!match) {
				return null;
			}
			return {
				negative: match[1] === '-',
				whole: match[2].replace(/^0+(?=\d)/, ''),
				fraction: (match[3] || '').padEnd(2, '0').slice(0, 2)
			};
		};

		const a = parseDecimal(first);
		const b = parseDecimal(second);
		if (!a || !b) {
			return first.localeCompare(second);
		}
		if (a.negative !== b.negative) {
			return a.negative ? -1 : 1;
		}

		let result = a.whole.length - b.whole.length;
		if (result === 0) {
			result = a.whole.localeCompare(b.whole);
		}
		if (result === 0) {
			result = a.fraction.localeCompare(b.fraction);
		}
		return a.negative ? -result : result;
	}

	document.querySelectorAll('.ventas-sort-button').forEach((button) => {
		button.addEventListener('click', () => {
			const table = button.closest('table');
			const body = table ? table.querySelector('tbody') : null;
			const heading = button.closest('th');
			if (!body || !heading) {
				return;
			}

			const columnIndex = Array.from(heading.parentElement.children).indexOf(heading);
			const direction = heading.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
			table.querySelectorAll('thead th').forEach((th) => {
				th.setAttribute('aria-sort', 'none');
				const indicator = th.querySelector('.ventas-sort-button span');
				if (indicator) {
					indicator.textContent = '↕';
				}
			});
			heading.setAttribute('aria-sort', direction);
			const indicator = button.querySelector('span');
			if (indicator) {
				indicator.textContent = direction === 'ascending' ? '↑' : '↓';
			}

			const rows = Array.from(body.querySelectorAll('tr')).filter((row) => row.children.length > columnIndex);
			const factor = direction === 'ascending' ? 1 : -1;
			rows.sort((rowA, rowB) => {
				const valueA = rowA.children[columnIndex].dataset.sortValue || '';
				const valueB = rowB.children[columnIndex].dataset.sortValue || '';
				if (valueA === '' || valueB === '') {
					return valueA === valueB ? 0 : (valueA === '' ? 1 : -1);
				}

				let result;
				if (button.dataset.sortType === 'decimal') {
					result = compareDecimals(valueA, valueB);
				} else if (button.dataset.sortType === 'date') {
					result = valueA.localeCompare(valueB);
				} else {
					result = Number(valueA) - Number(valueB);
				}
				return result * factor;
			});
			rows.forEach((row) => body.appendChild(row));
		});
	});
})();
