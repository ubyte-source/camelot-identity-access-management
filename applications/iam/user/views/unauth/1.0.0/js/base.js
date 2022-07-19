
(function (window) {

	'use strict';

	let pathname = window.location.pathname.split(String.fromCharCode(47));
	window.reference = pathname.slice(4);

	let title_text = document.createTextNode(window.page.getTranslate('problem.' + window.reference[0]));

	let title = document.createElement('div');
	title.classList.add('title');
	title.appendChild(title_text)
	window.page.elements.push(title);

	let container = document.createElement('div');
	container.className = 'layout';
	window.page.elements.push(container);

	let content = document.createElement('div');
	content.classList.add('form-buttons');
	window.page.elements.push(content);

	let submit = new Button();
	submit.getIcon().set('arrow_back');
	submit.setText(window.page.getTranslate('buttons.back'));
	submit.onClick(function () {
		window.location = String.fromCharCode(47);
	});

	content.appendChild(submit.out());

})(window);
