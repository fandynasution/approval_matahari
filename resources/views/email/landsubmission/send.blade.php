<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Type" content="application/pdf">
    <meta name="x-apple-disable-message-reformatting">
    <title>IFCA - Matahari Land</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ url('public/images/KuraKuraBali-iconew.ico') }}">
    
    <style>
        body {
            font-family: Arial;
            margin: 0;
            padding: 0 !important;
            mso-line-height-rule: exactly;
            background-color: #ffffff;
            font-family: Arial;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .custom-table {
                background-color:#e0e0e0;"
            }

        td {
            padding: 8px;
        }

        @media only screen and (max-width: 620px) {
            table {
                width: 100% !important;
            }

            td {
                display: block;
                width: 100% !important;
                box-sizing: border-box;
            }
            .custom-table {
                background-color:#ffffff;"
            }
            
        }
    </style>
</head>

<body width="100%" style="margin: 0; padding: 0 !important; mso-line-height-rule: exactly; background-color: #ffffff;font-family: Arial;">
	<div style="width: 100%; background-color: #ffffff; text-align: center;">
        <table width="80%" border="0" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="margin-left: auto;margin-right: auto;" >
            <tr>
               <td style="padding: 40px 0;">
                    <table style="width:100%;max-width:620px;margin:0 auto;">
                        <tbody>
                            <tr>
                                <td style="text-align: center; padding-bottom:25px">
                                    <img width = "120" src="{{ url('public/images/email_header.jpg') }}" alt="logo">
                                        <p style="font-size: 16px; color: #026735; padding-top: 0px;">{{ $dataArray['entity_name'] }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <table style="width:100%;max-width:620px;margin:0 auto;background-color:#e0e0e0;" class="custom-table">
                        <tbody>
                            <tr>
                                <td style="padding: 30px 30px">
                                    <h5 style="text-align:left;margin-bottom: 24px; color: #000000; font-size: 20px; font-weight: 400; line-height: 28px;">Dear {{ $dataArray['user_name'] }}, </h5>
                                    <p style="text-align:left;margin-bottom: 15px; color: #000000; font-size: 16px;">Below is a purchase order that requires your approval :</p>
                                    <p style="text-align:left; margin-bottom: 15px; margin-top: 0; color: #000000; font-size: 16px; list-style-type: circle;">

                                        <p style="text-align:left; margin-bottom: 15px; color: #000000; font-size: 16px;">
                                            <b>{{ $dataArray['desc_sub'] }}</b><br>
                                            Submission no : {{ $dataArray['submission_no'] }}<br>
                                        </p>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
               </td>
            </tr>
        </table>
        </div>
</body>
</html>