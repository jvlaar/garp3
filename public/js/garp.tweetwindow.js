Ext.ns('Garp');

Garp.TweetWindow = Ext.extend(Ext.util.Observable,{
	
	urlPart: '',
	width: 550,
	height: 460,
	x: 320,
	y: 120,
	bodyBorder: false,
	url: 'https://twitter.com/intent/tweet?',
	
	constructor: function(config){
		Ext.apply(this, config);
		this.winId = 'tweet-' + Ext.id();
		var opts = new Ext.Template('chrome=no,menubar=no,toolbar=no,scrollbars=no,width={width},height={height},left={left},top={top}');
		opts = opts.apply({
			width: this.width,
			height: this.height,
			left: this.x,
			top: this.y
		});
		this.win = window.open(this.url + this.urlPart, 'tweet-' + this.winId, opts);
		this.win.focus();
		Garp.TweetWindow.superclass.constructor.call(this, config);
	}
});

/**
 * Garp TweetField. Simple extension with default handlers.
 */
Garp.TweetField = Ext.extend(Ext.form.CompositeField,{

	fieldLabel: __('Twitter description'),
	
	isDirty: function(){
		if (this.twitterExcerpt && this.twitterExcerpt.rendered) {
			return this.twitterExcerpt.isDirty();
		}
		return false;
	},
	
	initComponent: function(){
		
		this.twitterExcerpt = new Ext.form.TextArea({
			'name': 'twitter_description',
			label: null,
			messageTarget: 'side',
			'fieldLabel': __('Twitter description'),
			'disabled': false,
			'hidden': false,
			'maxLength': 119,
			'countBox': 'twitterCount',
			flex: 1,
			ref: '../../../../twitterExcerpt',
			'allowBlank': true
		});
		
		this.items = [this.twitterExcerpt, {
			xtype: 'button',
			name: 'tweetBtn',
			ref: '../../../../tweetBtn',
			width: 32,
			handler: function(b, e){
				var fp = this.refOwner;
				if (!fp) {
					return;
				}
				if (fp.bitly_url.getValue()) {
					var msg = fp.twitterExcerpt.getValue();
					msg += ' ';
					msg += fp.bitly_url.getValue();
					var win = new Garp.TweetWindow({
						urlPart: Ext.urlEncode({
							'text': msg
						}),
						x: e.getPageX(),
						y: e.getPageY()
					});
				} else {
					Ext.Msg.alert('Garp', __('In order to tweet, you need to save your data first.'));
				}
			},
			iconCls: 'icon-twitter'
		}, {
			xtype: 'button',
			name: 'fbBtn',
			ref: '../../../../fbBtn',
			width: 32,
			handler: function(b, e){
				var fp = this.refOwner;
				if (!fp) {
					return;
				}
				if (fp.bitly_url.getValue()) {
					var msg = fp.twitterExcerpt.getValue();
					msg += ' ';
					msg += fp.bitly_url.getValue();
					var lm = new Ext.LoadMask(Ext.getBody(), {
						text: __('Please wait')
					});
					lm.show();
					FB.ui({
						method: 'feed',
						message: fp.twitterExcerpt.getValue(),
						link: fp.bitly_url.getValue(),
						redirectUri: CDN + BASE + 'g/content/admin'
					}, function(response){
						lm.hide();
					});
				} else {
					Ext.Msg.alert('Garp', __('In order to FB, you need to save your data first.'));
				}
			},
			iconCls: 'icon-fb'
		}, {
			xtype: 'button',
			name: 'inBtn',
			ref: '../../../../inBtn',
			width: 32,
			handler: function(b, e){
				var fp = this.refOwner;
				if (!fp) {
					return;
				}
				
				function confirmStatusUpdate(){
					if (fp.bitly_url.getValue()) {
						var msg = fp.twitterExcerpt.getValue();
						var link = fp.bitly_url.getValue();
						msg.replace('"', '\"');
						msg += ' ';
						msg += '<a href=\"' + link + '\">' + link + '</a>';
					}
					Ext.MessageBox.confirm(__('Garp'), __('Do you want to update your linkedIn status? <br>') + msg, function(btn){
						if (btn == 'yes') {
						
							var lm = new Ext.LoadMask(Ext.getBody(), {
								text: __('Please wait')
							});
							lm.show();
							updateURL = "/people/~/person-activities"
							IN.API.Raw(updateURL).method("POST").body('{"contentType":"linkedin-html","body":"' + msg + '"}').result(function(r){
								lm.hide();
							}).error(function(error){
								lm.hide();
								Ext.MessageBox.alert(__('Garp'), __('Something went wrong while updating your status'))
								console.log(error);
							});
						}
					});
				}
				
				if (IN.User.isAuthorized()) {
					confirmStatusUpdate();
				} else {
					IN.User.authorize(confirmStatusUpdate, this);
				}
			},
			iconCls: 'icon-linkedin'
		}, {
			xtype: 'displayfield',
			ref: '../../../../twitterCount',
			width: 60,
			cls: 'garp-countbox'
		}];
		
		Garp.TweetField.superclass.initComponent.call(this);
	}
});
Ext.reg('tweetfield',Garp.TweetField);
