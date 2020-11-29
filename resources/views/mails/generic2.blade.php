<?php

$bgColor = '#ffffff';
$bgColorPrimaryButton = '#FF2C55';
$bgColorPrimaryButtonHover = '#200043';
$orangeColor = '#ef5443';

//$title = $this->title;
//$description = $this->description;
//$button = $this->button;
//$extraContent = "";


?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
    <head>
        <meta charset="utf-8"> <!-- utf-8 works for most cases -->
        <meta name="viewport" content="width=device-width"> <!-- Forcing initial-scale shouldn't be necessary -->
        <meta http-equiv="X-UA-Compatible" content="IE=edge"> <!-- Use the latest (edge) version of IE rendering engine -->
        <meta name="x-apple-disable-message-reformatting">  <!-- Disable auto-scale in iOS 10 Mail entirely -->
        <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no"> <!-- Tell iOS not to automatically link certain text strings. -->
        <meta name="color-scheme" content="light">
        <meta name="supported-color-schemes" content="light">
        <title></title> <!-- The title tag shows in email notifications, like Android 4.4. -->

        <!-- What it does: Makes background images in 72ppi Outlook render at correct size. -->
        <!--[if gte mso 9]>
        <xml>
            <o:OfficeDocumentSettings>
                <o:AllowPNG/>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
        <![endif]-->

        <!-- Web Font / @font-face : BEGIN -->
        <!-- NOTE: If web fonts are not required, lines 23 - 41 can be safely removed. -->

        <!-- Desktop Outlook chokes on web font references and defaults to Times New Roman, so we force a safe fallback font. -->
        <!--[if mso]>
            <style>
                * {
                    font-family: sans-serif !important;
                }
            </style>
        <![endif]-->

        <!-- All other clients get the webfont reference; some will render the font and others will silently fail to the fallbacks. More on that here: http://stylecampaign.com/blog/2015/02/webfont-support-in-email/ -->
        <!--[if !mso]><!-->
        <!-- insert web font reference, eg: <link href='https://fonts.googleapis.com/css?family=Roboto:400,700' rel='stylesheet' type='text/css'> -->
        <!--<![endif]-->

        <!-- Web Font / @font-face : END -->

        <!-- CSS Reset : BEGIN -->
        <style>


        </style>
        <!-- CSS Reset : END -->

        <!-- Progressive Enhancements : BEGIN -->
        <style>

        </style>
        <!-- Progressive Enhancements : END -->

    </head>
    <body style="padding: 0;background: <?php echo $bgColor; ?>;">
        <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="<?php echo $bgColor; ?>">
            <tbody>
                <tr>
                    <td style="padding:15px">
                        <div style="text-align: center;">
                            <table width="550" cellspacing="0" cellpadding="0" align="center" bgcolor="<?php echo $bgColor; ?>">
                                <tbody>
                                    <tr>
                                        <td align="left">
                                            <div style="border:solid 1px <?php echo $bgColor; ?>;">
                                                <table align="center" style="text-align:center;line-height:1.6;font-size:12px;font-family:Helvetica,Arial,sans-serif;border:solid 1px #ffffff;color:#444;" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="<?php echo $bgColor; ?>">
                                                    <tbody>
                                                        <tr>
                                                            <td style="line-height:32px;padding:0px 30px 0px 30px;" valign="baseline">
                                                                <span>
                                                                    <div style="padding: 25px 0 0 0;">
                                                                        <img src="https://wwstage.devmds.com/storage/mails/logo-mailings.png" alt="" width="50" height="43" style="border: 0px solid transparent;"/>
                                                                        
                                                                    </div>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <table align="center" style="text-align:center;line-height:1.6;font-size:12px;font-family:Helvetica,Arial,sans-serif;border:solid 1px #ffffff;color:#444;" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="<?php echo $bgColor; ?>">
                                                    <tbody>
                                                        <tr>
                                                            <td style="line-height:32px;padding:0px 30px 0px 30px;" valign="baseline">
                                                                <span>
                                                                    <div style="border-bottom: 1px solid <?php echo $bgColor; ?>;padding: 0 0 25px 0;">
                                                                    <img src="https://wwstage.devmds.com/storage/mails/name-mailings.png" alt="" width="180" height="21" style="border: 0px solid transparent;"/>
                                                                    </div>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                
                                                <table width="550" align="center" style="text-align:center;padding:0;color:#444;line-height:1.6;font-size:12px;font-family:Arial,sans-serif;" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="<?php echo $bgColor; ?>">
                                                    <tbody>
                                                        <tr>
                                                            <td>
                                                                <div>
                                                                    <img src="https://wwstage.devmds.com/storage/mails/bus-welcome.png" alt="" width="200" height="163" style="border: 0px solid transparent;"/>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <table width="550" align="center" style="text-align:center;padding:20px 0 0 0;color:<?php echo $bgColorPrimaryButton; ?>;line-height:1.6;font-size:20px;font-weight:bold;font-family:Arial,sans-serif;" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="<?php echo $bgColor; ?>">
                                                    <tbody>
                                                        <tr>
                                                            <td>@lang('xx:Welcome to Wander World!')</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <table width="550" align="center" style="text-align:center;padding:10px 0;color:#000;line-height:1.6;font-size:15px;font-weight:normal;font-family:Arial,sans-serif;" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="<?php echo $bgColor; ?>">
                                                    <tbody>
                                                        <tr>
                                                            <td>@lang('xx:Click in the below button to activate your account and belongs to out travelers community')</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <table width="550" align="center" style="text-align:center;padding:20px 0;color:#fff;line-height:1.6;font-size:15px;font-weight:normal;font-family:Arial,sans-serif;" border="0" cellspacing="0" cellpadding="0" bgcolor="<?php echo $bgColor; ?>">
                                                    <tbody>
                                                        <tr>
                                                            <td>
                                                                <a href="https://wwstage.devmds.com/app/welcome" style="text-decoration: none;color: #fff;"><table width="200" align="center" style="text-align:center;padding:5px 15px;color:#fff;line-height:1.6;font-size:17px;font-weight:bold;font-family:Arial,sans-serif;border-radius: 25px;height: 50px;" border="0" cellspacing="0" cellpadding="0" bgcolor="<?php echo $bgColorPrimaryButton; ?>">
                                                                    <tbody>
                                                                        <tr>
                                                                            <td>@lang('xx:click here')</td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table></a>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
    
                                                <table align="center" style="text-align:center;line-height:1.5;font-size:12px;font-family:Arial,sans-serif;padding: 30px 0px;" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="<?php echo $bgColor; ?>">
                                                    <tbody>
                                                        <tr style="font-size:11px;color:#999999">
                                                            <td style="border-top:1px solid <?php echo $bgColor; ?>;padding-top:10px;">
    															@lang('xx:Do you have any doubt? Contact us to give you the help you need. WanderWorld.')
                                                            </td>
                                                        </tr>
                                                        <tr style="font-size:11px;color:#999999">
                                                            <td style="border-top:1px solid <?php echo $bgColor; ?>;padding-top:10px;">
    															@lang('xx:2020 Copyright @ All right reserved.')
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </body>
</html>
