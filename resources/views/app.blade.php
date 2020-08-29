<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ setting('admin.title') }}</title>

        @if( setting('admin.icon_image') )
        <link rel="icon" type="image/png" href="{{ Voyager::image( setting('admin.icon_image') ) }}" sizes="any" />
        @endif

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">

        <!-- Styles -->
        <style>
            html, body {
                background-color: #fff;
                color: #636b6f;
                font-family: 'Nunito', sans-serif;
                font-weight: 200;
                height: 100vh;
                margin: 0;
            }

            .full-height {
                height: 100vh;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }

            .position-ref {
                position: relative;
            }

            .top-right {
                position: absolute;
                right: 10px;
                top: 18px;
            }

            .content {
                text-align: center;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #ffffff;
                padding: 0 25px;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }
            .bg-logo {
                background-color: #fff;
                background-position: center;
                background-repeat: no-repeat;
                background-size: cover;
            }

            .wrap {
                display: block;
                margin: 0 auto;
                max-width: 200px;
                text-align: center;
            }

            .btn {
                cursor: pointer;
                text-transform: uppercase;
                text-decoration: none;
                display: block;
                padding: 5px;
                border-radius: 15px;
                background-color: #00eeaa;
                border: 1px solid #00eeaa;
                color: #fff;
                font-weight: bold;
                max-width: 200px;
                outline: none;
                width: 100%;
            }
            .btn:hover {
                opacity: 0.8;
            }
        </style>
    </head>
    <body class="bg-logo">
            <div class="wrap">
                <img src="{{ Voyager::image('front/logo@200x86.png') }}" alt="Wander World" />
                <p>@lang('app.message_info_continue')</p>
                <button class="btn" onclick="openApp();">@lang('app.continue_to_app')</button>
            </div>
            <script type="text/javascript">
            Object.defineProperty(window, 'mlzl_loading', {
                get: function() {
                    return function() {
                        var options = {
                            text: 'Loading',
                            action: 'start',
                        };

                        var args = arguments[0] ? arguments[0] : {};

                        for(var i in options) {
                            if( !args[i] ) {
                                args[i] = options[i];
                            }
                        }

                        var id_panel = 'ldg_mdfsdf_panel1014';

                        if(args.action == 'start') {

                            if(	document.getElementById(id_panel) ) {
                                return;
                            }

                            window.dotsAnimation = window.setInterval( function() {
                                var dots = document.getElementById('dots_1014');
                                if( dots.innerHTML.length == 3) {
                                    dots.innerHTML = '';
                                } else {
                                    dots.innerHTML = dots.innerHTML+'.';
                                }
                            }, 1000);

                            var element = document.createElement('div');
                            element.setAttribute('id', id_panel);
                            element.style.display = 'block';
                            element.style.position = 'fixed';
                            element.style.left = '0';
                            element.style.top = '0';
                            element.style.width = '100%';
                            element.style.height = '100%';
                            element.style.background = 'rgba(255,255,255,0.5)';
                            element.style.zIndex = '9999';
                            element.innerHTML = '<span style="color: #fff;position: absolute;left: 50%;top: 50%;transform: translate(-50%,-50%);font-style: italic;font-size: 25px;font-weight: bold;background: rgba(0,0,0,0.9);border-radius:20px;padding: 20px 40px 20px 30px;">'+args.text+'<i id="dots_1014" style="text-decoration: none;position: absolute;"></i></span>';

                            document.body.appendChild(element);
                        } else if(args.action == 'stop') {
                            if(window.dotsAnimation) {
                                clearInterval(window.dotsAnimation);
                            }
                            if(	document.getElementById(id_panel) ) {
                                var elem = document.getElementById(id_panel);
                                elem.parentNode.removeChild(elem);
                            }
                        }
                    };
                }
            });

            Object.defineProperty(window, 'openApp', {
                get: function (){
                    return function() {
                        var deepLinkAndroid = "{{ env('DEEPLINK_ANDROID','wanderworld://').$screen }}";
                        var deepLinkIOS = "{{ env('DEEPLINK_IOS','wanderworld://').$screen }}";

                        var deepLink = null;

                        if (MobileEsp.DetectIos()) {
                            deepLink = deepLinkAndroid;
                        } else if(MobileEsp.DetectAndroid()) {
                            deepLink = deepLinkIOS;
                        }

                        if (!deepLink) {
                            mlzl_loading({
                                text: "@lang('app.info_not_device')"
                            });
                            return;
                        }
                        window.open(deepLink);
                    };
                }
            });


            var MobileEsp={initCompleted:!1,isWebkit:!1,isMobilePhone:!1,isIphone:!1,isAndroid:!1,isAndroidPhone:!1,isTierTablet:!1,isTierIphone:!1,isTierRichCss:!1,isTierGenericMobile:!1,engineWebKit:"webkit",deviceIphone:"iphone",deviceIpod:"ipod",deviceIpad:"ipad",deviceMacPpc:"macintosh",deviceAndroid:"android",deviceGoogleTV:"googletv",deviceWinPhone7:"windows phone os 7",deviceWinPhone8:"windows phone 8",deviceWinPhone10:"windows phone 10",deviceWinMob:"windows ce",deviceWindows:"windows",deviceIeMob:"iemobile",devicePpc:"ppc",enginePie:"wm5 pie",deviceBB:"blackberry",deviceBB10:"bb10",vndRIM:"vnd.rim",deviceBBStorm:"blackberry95",deviceBBBold:"blackberry97",deviceBBBoldTouch:"blackberry 99",deviceBBTour:"blackberry96",deviceBBCurve:"blackberry89",deviceBBCurveTouch:"blackberry 938",deviceBBTorch:"blackberry 98",deviceBBPlaybook:"playbook",deviceSymbian:"symbian",deviceSymbos:"symbos",deviceS60:"series60",deviceS70:"series70",deviceS80:"series80",deviceS90:"series90",devicePalm:"palm",deviceWebOS:"webos",deviceWebOStv:"web0s",deviceWebOShp:"hpwos",deviceNuvifone:"nuvifone",deviceBada:"bada",deviceTizen:"tizen",deviceMeego:"meego",deviceSailfish:"sailfish",deviceUbuntu:"ubuntu",deviceKindle:"kindle",engineSilk:"silk-accelerated",engineBlazer:"blazer",engineXiino:"xiino",vndwap:"vnd.wap",wml:"wml",deviceTablet:"tablet",deviceBrew:"brew",deviceDanger:"danger",deviceHiptop:"hiptop",devicePlaystation:"playstation",devicePlaystationVita:"vita",deviceNintendoDs:"nitro",deviceNintendo:"nintendo",deviceWii:"wii",deviceXbox:"xbox",deviceArchos:"archos",engineFirefox:"firefox",engineOpera:"opera",engineNetfront:"netfront",engineUpBrowser:"up.browser",deviceMidp:"midp",uplink:"up.link",engineTelecaQ:"teleca q",engineObigo:"obigo",devicePda:"pda",mini:"mini",mobile:"mobile",mobi:"mobi",smartTV1:"smart-tv",smartTV2:"smarttv",maemo:"maemo",linux:"linux",mylocom2:"sony/com",manuSonyEricsson:"sonyericsson",manuericsson:"ericsson",manuSamsung1:"sec-sgh",manuSony:"sony",manuHtc:"htc",svcDocomo:"docomo",svcKddi:"kddi",svcVodafone:"vodafone",disUpdate:"update",uagent:"",InitDeviceScan:function(){this.initCompleted=!1,navigator&&navigator.userAgent&&(this.uagent=navigator.userAgent.toLowerCase()),this.isWebkit=this.DetectWebkit(),this.isIphone=this.DetectIphone(),this.isAndroid=this.DetectAndroid(),this.isAndroidPhone=this.DetectAndroidPhone(),this.isMobilePhone=this.DetectMobileQuick(),this.isTierIphone=this.DetectTierIphone(),this.isTierTablet=this.DetectTierTablet(),this.isTierRichCss=this.DetectTierRichCss(),this.isTierGenericMobile=this.DetectTierOtherPhones(),this.initCompleted=!0},DetectIphone:function(){return this.initCompleted||this.isIphone?this.isIphone:this.uagent.search(this.deviceIphone)>-1&&(!this.DetectIpad()&&!this.DetectIpod())},DetectIpod:function(){return this.uagent.search(this.deviceIpod)>-1},DetectIphoneOrIpod:function(){return!(!this.DetectIphone()&&!this.DetectIpod())},DetectIpad:function(){return!!(this.uagent.search(this.deviceIpad)>-1&&this.DetectWebkit())},DetectIos:function(){return!(!this.DetectIphoneOrIpod()&&!this.DetectIpad())},DetectAndroid:function(){return this.initCompleted||this.isAndroid?this.isAndroid:!!(this.uagent.search(this.deviceAndroid)>-1||this.DetectGoogleTV())},DetectAndroidPhone:function(){return this.initCompleted||this.isAndroidPhone?this.isAndroidPhone:!!this.DetectAndroid()&&(this.uagent.search(this.mobile)>-1||!!this.DetectOperaMobile())},DetectAndroidTablet:function(){return!!this.DetectAndroid()&&(!this.DetectOperaMobile()&&!(this.uagent.search(this.mobile)>-1))},DetectAndroidWebKit:function(){return!(!this.DetectAndroid()||!this.DetectWebkit())},DetectGoogleTV:function(){return this.uagent.search(this.deviceGoogleTV)>-1},DetectWebkit:function(){return this.initCompleted||this.isWebkit?this.isWebkit:this.uagent.search(this.engineWebKit)>-1},DetectWindowsPhone:function(){return!!(this.DetectWindowsPhone7()||this.DetectWindowsPhone8()||this.DetectWindowsPhone10())},DetectWindowsPhone7:function(){return this.uagent.search(this.deviceWinPhone7)>-1},DetectWindowsPhone8:function(){return this.uagent.search(this.deviceWinPhone8)>-1},DetectWindowsPhone10:function(){return this.uagent.search(this.deviceWinPhone10)>-1},DetectWindowsMobile:function(){return!this.DetectWindowsPhone()&&(this.uagent.search(this.deviceWinMob)>-1||this.uagent.search(this.deviceIeMob)>-1||this.uagent.search(this.enginePie)>-1||(this.uagent.search(this.devicePpc)>-1&&!(this.uagent.search(this.deviceMacPpc)>-1)||this.uagent.search(this.manuHtc)>-1&&this.uagent.search(this.deviceWindows)>-1))},DetectBlackBerry:function(){return this.uagent.search(this.deviceBB)>-1||this.uagent.search(this.vndRIM)>-1||!!this.DetectBlackBerry10Phone()},DetectBlackBerry10Phone:function(){return this.uagent.search(this.deviceBB10)>-1&&this.uagent.search(this.mobile)>-1},DetectBlackBerryTablet:function(){return this.uagent.search(this.deviceBBPlaybook)>-1},DetectBlackBerryWebKit:function(){return!!(this.DetectBlackBerry()&&this.uagent.search(this.engineWebKit)>-1)},DetectBlackBerryTouch:function(){return!(!this.DetectBlackBerry()||!(this.uagent.search(this.deviceBBStorm)>-1||this.uagent.search(this.deviceBBTorch)>-1||this.uagent.search(this.deviceBBBoldTouch)>-1||this.uagent.search(this.deviceBBCurveTouch)>-1))},DetectBlackBerryHigh:function(){return!this.DetectBlackBerryWebKit()&&!(!this.DetectBlackBerry()||!(this.DetectBlackBerryTouch()||this.uagent.search(this.deviceBBBold)>-1||this.uagent.search(this.deviceBBTour)>-1||this.uagent.search(this.deviceBBCurve)>-1))},DetectBlackBerryLow:function(){return!!this.DetectBlackBerry()&&(!this.DetectBlackBerryHigh()&&!this.DetectBlackBerryWebKit())},DetectS60OssBrowser:function(){return!!this.DetectWebkit()&&(this.uagent.search(this.deviceS60)>-1||this.uagent.search(this.deviceSymbian)>-1)},DetectSymbianOS:function(){return!!(this.uagent.search(this.deviceSymbian)>-1||this.uagent.search(this.deviceS60)>-1||this.uagent.search(this.deviceSymbos)>-1&&this.DetectOperaMobile||this.uagent.search(this.deviceS70)>-1||this.uagent.search(this.deviceS80)>-1||this.uagent.search(this.deviceS90)>-1)},DetectPalmOS:function(){return!this.DetectPalmWebOS()&&(this.uagent.search(this.devicePalm)>-1||this.uagent.search(this.engineBlazer)>-1||this.uagent.search(this.engineXiino)>-1)},DetectPalmWebOS:function(){return this.uagent.search(this.deviceWebOS)>-1},DetectWebOSTablet:function(){return this.uagent.search(this.deviceWebOShp)>-1&&this.uagent.search(this.deviceTablet)>-1},DetectWebOSTV:function(){return this.uagent.search(this.deviceWebOStv)>-1&&this.uagent.search(this.smartTV2)>-1},DetectOperaMobile:function(){return this.uagent.search(this.engineOpera)>-1&&(this.uagent.search(this.mini)>-1||this.uagent.search(this.mobi)>-1)},DetectKindle:function(){return this.uagent.search(this.deviceKindle)>-1&&!this.DetectAndroid()},DetectAmazonSilk:function(){return this.uagent.search(this.engineSilk)>-1},DetectGarminNuvifone:function(){return this.uagent.search(this.deviceNuvifone)>-1},DetectBada:function(){return this.uagent.search(this.deviceBada)>-1},DetectTizen:function(){return this.uagent.search(this.deviceTizen)>-1&&this.uagent.search(this.mobile)>-1},DetectTizenTV:function(){return this.uagent.search(this.deviceTizen)>-1&&this.uagent.search(this.smartTV1)>-1},DetectMeego:function(){return this.uagent.search(this.deviceMeego)>-1},DetectMeegoPhone:function(){return this.uagent.search(this.deviceMeego)>-1&&this.uagent.search(this.mobi)>-1},DetectFirefoxOS:function(){return!(!this.DetectFirefoxOSPhone()&&!this.DetectFirefoxOSTablet())},DetectFirefoxOSPhone:function(){return!(this.DetectIos()||this.DetectAndroid()||this.DetectSailfish())&&(this.uagent.search(this.engineFirefox)>-1&&this.uagent.search(this.mobile)>-1)},DetectFirefoxOSTablet:function(){return!(this.DetectIos()||this.DetectAndroid()||this.DetectSailfish())&&(this.uagent.search(this.engineFirefox)>-1&&this.uagent.search(this.deviceTablet)>-1)},DetectSailfish:function(){return this.uagent.search(this.deviceSailfish)>-1},DetectSailfishPhone:function(){return!!(this.DetectSailfish()&&this.uagent.search(this.mobile)>-1)},DetectUbuntu:function(){return!(!this.DetectUbuntuPhone()&&!this.DetectUbuntuTablet())},DetectUbuntuPhone:function(){return this.uagent.search(this.deviceUbuntu)>-1&&this.uagent.search(this.mobile)>-1},DetectUbuntuTablet:function(){return this.uagent.search(this.deviceUbuntu)>-1&&this.uagent.search(this.deviceTablet)>-1},DetectDangerHiptop:function(){return this.uagent.search(this.deviceDanger)>-1||this.uagent.search(this.deviceHiptop)>-1},DetectSonyMylo:function(){return this.uagent.search(this.manuSony)>-1&&(this.uagent.search(this.qtembedded)>-1||this.uagent.search(this.mylocom2)>-1)},DetectMaemoTablet:function(){return this.uagent.search(this.maemo)>-1||!(!(this.uagent.search(this.linux)>-1&&this.uagent.search(this.deviceTablet)>-1&&this.DetectWebOSTablet())||this.DetectAndroid())},DetectArchos:function(){return this.uagent.search(this.deviceArchos)>-1},DetectGameConsole:function(){return!!(this.DetectSonyPlaystation()||this.DetectNintendo()||this.DetectXbox())},DetectSonyPlaystation:function(){return this.uagent.search(this.devicePlaystation)>-1},DetectGamingHandheld:function(){return this.uagent.search(this.devicePlaystation)>-1&&this.uagent.search(this.devicePlaystationVita)>-1},DetectNintendo:function(){return this.uagent.search(this.deviceNintendo)>-1||this.uagent.search(this.deviceWii)>-1||this.uagent.search(this.deviceNintendoDs)>-1},DetectXbox:function(){return this.uagent.search(this.deviceXbox)>-1},DetectBrewDevice:function(){return this.uagent.search(this.deviceBrew)>-1},DetectSmartphone:function(){return!!(this.DetectTierIphone()||this.DetectS60OssBrowser()||this.DetectSymbianOS()||this.DetectWindowsMobile()||this.DetectBlackBerry()||this.DetectMeegoPhone()||this.DetectPalmOS())},DetectMobileQuick:function(){return this.initCompleted||this.isMobilePhone?this.isMobilePhone:!this.DetectTierTablet()&&(!!this.DetectSmartphone()||(this.uagent.search(this.mobile)>-1||(!!this.DetectOperaMobile()||(!(!this.DetectKindle()&&!this.DetectAmazonSilk())||(!!(this.uagent.search(this.deviceMidp)>-1||this.DetectBrewDevice())||(this.uagent.search(this.engineObigo)>-1||this.uagent.search(this.engineNetfront)>-1||this.uagent.search(this.engineUpBrowser)>-1))))))},DetectMobileLong:function(){return!!this.DetectMobileQuick()||(!!this.DetectGameConsole()||(!!(this.DetectDangerHiptop()||this.DetectMaemoTablet()||this.DetectSonyMylo()||this.DetectArchos())||(this.uagent.search(this.devicePda)>-1&&!(this.uagent.search(this.disUpdate)>-1)||(this.uagent.search(this.manuSamsung1)>-1||this.uagent.search(this.manuSonyEricsson)>-1||this.uagent.search(this.manuericsson)>-1||this.uagent.search(this.svcDocomo)>-1||this.uagent.search(this.svcKddi)>-1||this.uagent.search(this.svcVodafone)>-1))))},DetectTierTablet:function(){return this.initCompleted||this.isTierTablet?this.isTierTablet:!!(this.DetectIpad()||this.DetectAndroidTablet()||this.DetectBlackBerryTablet()||this.DetectFirefoxOSTablet()||this.DetectUbuntuTablet()||this.DetectWebOSTablet())},DetectTierIphone:function(){return this.initCompleted||this.isTierIphone?this.isTierIphone:!!(this.DetectIphoneOrIpod()||this.DetectAndroidPhone()||this.DetectWindowsPhone()||this.DetectBlackBerry10Phone()||this.DetectPalmWebOS()||this.DetectBada()||this.DetectTizen()||this.DetectFirefoxOSPhone()||this.DetectSailfishPhone()||this.DetectUbuntuPhone()||this.DetectGamingHandheld())||!(!this.DetectBlackBerryWebKit()||!this.DetectBlackBerryTouch())},DetectTierRichCss:function(){return this.initCompleted||this.isTierRichCss?this.isTierRichCss:!(this.DetectTierIphone()||this.DetectKindle()||this.DetectTierTablet())&&(!!this.DetectMobileQuick()&&(!!this.DetectWebkit()||!!(this.DetectS60OssBrowser()||this.DetectBlackBerryHigh()||this.DetectWindowsMobile()||this.uagent.search(this.engineTelecaQ)>-1)))},DetectTierOtherPhones:function(){return this.initCompleted||this.isTierGenericMobile?this.isTierGenericMobile:!(this.DetectTierIphone()||this.DetectTierRichCss()||this.DetectTierTablet())&&!!this.DetectMobileLong()}};MobileEsp.InitDeviceScan();
            </script>
    </body>
</html>
