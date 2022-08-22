
(function (window) {

	'use strict';

	window.getHostnameFromUrl = function (url) {
		let matches = url.match(/^https?\:\/\/([^\/?#]+)(?:[\/?#]|$)/i);
		return matches && matches[1];
	}

	let oauth = function () {
		widgets.form.ad.request(function (json) {
			window.submit.getLoader().remove();
			if (json.hasOwnProperty('authorize')) window.location.href = json.authorize;
			delete widgets.form.ad.signin;
		});
	};

	let widgets = window.page.getWidgets();

	widgets.bumblebee = new Bumblebee();
	let nation = navigator.language.substring(0, 2);
	if (window.page.bumblebee.locale.hasOwnProperty(nation)) widgets.bumblebee.getBubble().getSpeech().setLanguageCode(window.page.bumblebee.locale[nation]);
	widgets.bumblebee.getBubble().getSpeech().setAPIKey(window.page.bumblebee.apikey);

	window.page.addHTMLElement(widgets.bumblebee.out());

	window.submit = new Button();
	window.submit.getIcon().set('send');
	window.submit.setText(window.page.getTranslate('buttons.login'));
	window.submit.onClick(function () {
		if (widgets.form.ad.getManager().status()
			|| widgets.form.plain.getManager().status()) return;

		this.getLoader().apply(window.page.getTranslate('buttons.loader'));

		typeof widgets.form.ad.signin === 'function'
			? widgets.form.ad.signin()
			: widgets.form.plain.login();
	});

	let container = document.createElement('div');
	container.className = 'buttons-form';
	container.appendChild(window.submit.out());

	window.back = new Button();
	window.back.getIcon().set('arrow_back');
	window.back.setText(window.page.getTranslate('buttons.back'));
	window.back.addStyle('red');
	window.back.out().classList.add('split');
	window.back.onClick(function () {
		container.removeChild(this.out());
		window.submit.out().classList.remove('send');
		window.submit.out().classList.remove('split');
		widgets.form.ad.reset();
		widgets.form.plain.reset();
		login.removeChild(widgets.form.plain.out());
		login.insertBefore(widgets.form.ad.out(), container);
		widgets.form.ad.signin = oauth;
	});

	let main = document.createElement('div');
	main.id = 'main';
	main.className = 'pure-g';
	window.page.elements.push(main);

	let wrapper = document.createElement('div');
	wrapper.id = 'wrapper';
	wrapper.className = 'pure-u-24-24 pure-u-lg-5-24';
	main.appendChild(wrapper);

	let content = document.createElement('div');
	content.id = 'content';
	content.className = 'pure-u-24-24';
	wrapper.appendChild(content);

	widgets.tabs = new Tabs();
	content.appendChild(widgets.tabs.out());

	let register = document.createElement('div');
	register.id = 'register';
	register.className = 'pure-u-24-24';
	content.appendChild(register);

	let login = document.createElement('div');
	login.id = 'login';
	login.className = 'pure-u-24-24';
	content.appendChild(login);

	let tab_login = widgets.tabs.addItem(window.page.getTranslate('tabs.login'), login, 'material-icons person');
	tab_login.show().out();

	widgets.bumblebee.getTutorial().add(function () {
		tab_login.show()
		this.getBubble().setText(window.page.getTranslate('tutorial.hello'));
		this.getBubble().show();
	});
	widgets.bumblebee.getTutorial().add(function () {
		this.getBubble().setText(window.page.getTranslate('tutorial.info'));
		this.getBubble().show();
	});

	widgets.bumblebee.getTutorial().add(function () {
		this.addLighten(tab_login.out());
		this.getBubble().setText(window.page.getTranslate('tutorial.login'));
		this.getBubble().show()
	});

	widgets.bumblebee.getTutorial().add(function () {
		this.getBubble().setText(window.page.getTranslate('tutorial.ad'));
		this.getBubble().show()
	});

	let tabs_register = widgets.tabs.addItem(window.page.getTranslate('tabs.register'), register, 'material-icons person_add');
	tabs_register.out();

	widgets.bumblebee.getTutorial().add(function () {
		this.addLighten(tabs_register.out());
		tabs_register.show();
		this.getBubble().setText(window.page.getTranslate('tutorial.register'));
		this.getBubble().show()
	});

	widgets.bumblebee.getTutorial().add(function () {
		this.getBubble().setText(window.page.getTranslate('tutorial.passphrase'));
		this.getBubble().show()
	});

	///////// LOGIN

	widgets.form = new window.Page.Widget.Organizer();

	widgets.form.ad = new Form();
	widgets.form.ad.setRequestUrl('/api/sso/oauth/signin');
	widgets.form.ad.getManager().show(true);
	widgets.form.ad.setCallbackFail(function () {
		login.removeChild(widgets.form.ad.out());
		let values = widgets.form.ad.get();
		container.insertBefore(window.back.out(), container.firstChild);
		login.insertBefore(widgets.form.plain.out(), login.firstChild);
		window.submit.out().classList.add('send');
		window.submit.out().classList.add('split');
		widgets.form.plain.set('email', values.email);
	});
	widgets.form.ad.signin = oauth;

	window.page.tables.email.fields.reverse();

	for (let item = 0; item < window.page.tables.email.fields.length; item++) {
		if (window.page.tables.email.fields[item].protected === true) continue;
		widgets.form.ad.addInput(window.page.tables.email.fields[item]).getInput().addEventListener('keypress', function (ev) {
			if (ev.keyCode === 13) {
				widgets.form.ad.signin();
				ev.preventDefault();
			}
		}, false);
	}

	login.appendChild(widgets.form.ad.out());

	widgets.form.plain = new Form();
	widgets.form.plain.setRequestUrl('/api/iam/user/login');
	widgets.form.plain.getManager().show(true);
	widgets.form.plain.setCallbackSuccess(function () {
		let xhr = this.getXHR(),
			response = JSON.parse(xhr.responseText),
			return_url = Page.getUrlParameter(window.page.sso);

		switch (true) {
			case response.hasOwnProperty(window.page.authorization) && !!return_url:
				let decrypt = atob(return_url),
					hostname = window.getHostnameFromUrl(decrypt),
					destination = 'https://' + hostname + '/';

				if (destination === window.page.host) {
					window.location = decrypt;
					break;
				}

				window.location = destination
					+ 'api'
					+ String.fromCharCode(47)
					+ window.page.authorization
					+ String.fromCharCode(47)
					+ btoa(response.authorization)
					+ String.fromCharCode(63)
					+ window.page.sso
					+ String.fromCharCode(61)
					+ return_url;
				break;
			default:
				window.location = String.fromCharCode(47);
		}
	});
	widgets.form.plain.login = function () {
		let email = widgets.form.plain.findContainer('email');
		email.setEditable(true);
		widgets.form.plain.request(function () {
			email.setEditable(false);
			window.submit.getLoader().remove();
		});
	}

	window.page.tables.password.fields.reverse();

	for (let item = 0; item < window.page.tables.password.fields.length; item++) {
		if (window.page.tables.password.fields[item].name === 'email') window.page.tables.password.fields[item][Form.Container.editable()] = false;
		if (window.page.tables.password.fields[item].protected === true) continue;
		widgets.form.plain.addInput(window.page.tables.password.fields[item]).getInput().addEventListener('keypress', function (ev) {
			if (ev.keyCode === 13) {
				widgets.form.plain.login();
				ev.preventDefault();
			}
		}, false);
	}

	login.appendChild(container);

	///// REGISTER

	let container_register = document.createElement('div');
	container_register.className = 'buttons-form';

	widgets.form.register = new Form();
	widgets.form.register.setRequestUrl('/api/iam/user/register');
	////
	widgets.form.register.setCallbackSuccess(function () {
		let values = widgets.form.register.get();

		tab_login.show();

		widgets.form.ad.set('email', values.email);
		widgets.form.register.reset();
	});

	for (let item = 0; item < window.page.tables.register.fields.length; item++)
		if (window.page.tables.register.fields[item].protected !== true) {
			widgets.form.register.addInput(window.page.tables.register.fields[item]);
			let parameter_return = Page.getUrlParameter('return_url'),
				parameter_return_decoded = typeof parameter_return !== 'undefined'
					? atob(parameter_return).substring(1 + atob(parameter_return).indexOf(String.fromCharCode(63)))
					: undefined;

			let parameter = Page.getUrlParameter(window.page.tables.register.fields[item].name, parameter_return_decoded);
			if (parameter)
				widgets.form.register.set(window.page.tables.register.fields[item].name, parameter);
		}

	widgets.modal = new Modal();

	let notice_node = document.createTextNode(window.page.getTranslate('modal.info.notice')), notice_paragraph = document.createElement('p');
	notice_paragraph.appendChild(notice_node);
	widgets.modal.addContent(notice_paragraph);

	let picture = document.createElement('p'),
		image = document.createElement('img');
	image.src = 'https://public.energia-europa.com/image/help-qr-example.png';
	picture.id = "img";
	picture.appendChild(image)
	widgets.modal.addContent(picture);

	widgets.modal.setActionShow(function () {
		widgets.modal.setTitle(window.page.getTranslate('modal.info.title'));
	});
	window.page.elements.push(widgets.modal.out());

	widgets.form.register.getRow('device_passphrase_value').addButton('help').getButton().addEventListener('click', function (event) {
		widgets.modal.show();
	});

	widgets.bumblebee.getTutorial().add(function () {
		let button = widgets.form.register.getRow('device_passphrase_value').getActions()[0].getButton();
		this.addLighten(button);
		this.getBubble().setText(window.page.getTranslate('tutorial.modal'));
		this.getBubble().show()
	}).setTerminator(function () {
		widgets.modal.show();
		setTimeout(function (modal) {
			modal.hide();
		}, 2048, widgets.modal)
	});

	register.appendChild(widgets.form.register.out());

	window.register = new Button();
	window.register.getIcon().set('add');
	window.register.setText(window.page.getTranslate('buttons.register'));
	window.register.onClick(function () {
		widgets.form.register.request();
	});

	container_register.appendChild(window.register.out());
	register.appendChild(container_register);

	widgets.tabs.addItem(window.page.getTranslate('tabs.tutorial'), document.createElement('p'), 'material-icons school').out().addEventListener('click', function () {
		tab_login.show()
		widgets.bumblebee.getTutorial().play();
	})

})(window);
