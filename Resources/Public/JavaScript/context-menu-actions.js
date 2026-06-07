/**
 * Module: @thieleundklose/autotranslate/context-menu-actions
 *
 * JavaScript to handle the click action of the "Hello World" context menu item
 */

class ContextMenuActions {

	helloWorld(table, uid) {
		// if (table === 'pages') {
			//If needed, you can access other 'data' attributes here from $(this).data('someKey')
			//see item provider getAdditionalAttributes method to see how to pass custom data attributes
			// top.TYPO3.Notification.error('Hello World Page table:', 'Hi there!', 5);
			top.TYPO3.Notification.error('Hello World Page', 'Hi there! table:' + table + ' uid: ' + uid, 5);

		// }
	};
}

export default new ContextMenuActions();
