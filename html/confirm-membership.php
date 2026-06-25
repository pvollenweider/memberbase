<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Casa Alianza Membership</title>

    <!-- Bootstrap core CSS -->
    <link href="plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body>



<div class="container">
    <div class="starter-template">
        <h1>Casa Alianza Suisse</h1>
        <p class="lead">Gestion des membres.</p>
        <?php
        $charset = "UTF-8";
        header("Content-Type: text/html; charset=$charset");

        include "locales/resources_fr.inc";
        include "includes/declarations.inc";
        include "classes/user_class.inc";
        include "classes/team_class.inc";
        include "classes/compta_class.inc";
        include "classes/property_class.inc";
        include "classes/metagroup_class.inc";
        require '/usr/share/php/libphp-phpmailer/PHPMailerAutoload.php';

        if (isset($_REQUEST['email'])) {
            $email = $_REQUEST['email'];

            $user = new User();
            $user->lookupUserByEmail($email);

            $mail = new PHPMailer;
            #$mail->SMTPDebug = 1; //2 for both client and server side response
            $mail->isSMTP();
            $mail->Debugoutput = 'html';
            $mail->Host = 'smtp.gmail.com';
            $mail->Port = 587;
            $mail->SMTPSecure = 'tls';
            $mail->SMTPAuth = true;
            $mail->Username = "polito@gmail.com";//sender's gmail address
            $mail->Password = "dtffykihnkqwxnvm";//sender's password

            if($user->getId() === NULL) {
                $mail->setFrom('pol@casa-alianza.ch', 'Philippe Vollenweider');//sender's incormation
                $mail->addReplyTo('info@casa-alianza.ch', 'Casa Alianza Suisse');//if alternative reply to address is being used
                $mail->addAddress('pvollenweider@jahia.com', 'Philippe Vollenweier');//receiver's information
                $mail->AltBody = "$email";
                $mail->Subject = "Casa Alianza Membership to Add: $email";//subject of the email
                $mail->msgHTML("Hello<br/><br/>$email want to be a member now, but we are not able to get contact in the database. Please try to get in touch with him");
                if (!$mail->send()) {
                    echo 'Message could not be sent.';
                    echo 'Mailer Error: ' . $mail->ErrorInfo;
                } else {
                    echo "<div class='alert alert-success' role='alert'>Bonjour, nous avons bien reçu votre demande, cependant nous ne parvenons pas à associer vôtre email $email avec un membre de notre base. Merci de contacter notre bureau au 022 321 82 86.</div>";
                }
            } else {
                $firstname=$user->getFirstName();
                $lastname=$user->getLastName();
                $user->addMembership(117);

                $mail->setFrom('pol@casa-alianza.ch', 'Philippe Vollenweider');//sender's incormation
                $mail->addReplyTo('info@casa-alianza.ch', 'Casa Alianza Suisse');//if alternative reply to address is being used
                $mail->addAddress('pvollenweider@jahia.com', 'Philippe Vollenweier');//receiver's information
                $mail->AltBody = "$email";
                $mail->Subject = "Casa Alianza Membership Confirmation from $email";//subject of the email
                $mail->msgHTML("Hello<br/><br/>$firstname $lastname $email has confirmed to be a member now");
                if (!$mail->send()) {
                    echo 'Une erreur est survenue durant le processus. Merci de réessayer plus tard ou de contacter le bureau de Casa Alianza au 022 320 82 86.';
                    echo 'Mailer Error: ' . $mail->ErrorInfo;
                }

                $mail->setFrom('info@casa-alianza.ch', 'Casa Alianza Suisse');//sender's incormation
                $mail->addReplyTo('info@casa-alianza.ch', 'Casa Alianza Suisse');//if alternative reply to address is being used
                $mail->addAddress($email, "$firstname $lastname");//receiver's information
                $mail->AltBody = 'Nous vous confirmons que nous avons pris en compte votre demande de devenir membre de Casa Alianza à part entière.';
                $mail->Subject = 'Confirmation de Casa Alianza';//subject of the email
                $mail->msgHTML("Bonjour $firstname,<br/><br/>Nous vous confirmons que nous avons pris en compte votre demande de devenir membre de Casa Alianza à part entière. <br/><br/>Nous restons à votre disposition pour toutes questions supplémentaires.<br/><br/>L'équipe de Casa Alianza");
                if (!$mail->send()) {
                    echo '<div class="alert alert-danger" role="alert">';
                    echo 'Une erreur est survenue durant le processus. Merci de réessayer plus tard ou de contacter le bureau de Casa Alianza au 022 320 82 86.';
                    echo 'Mailer Error: ' . $mail->ErrorInfo;
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-success" role="alert">Merci, nous avons bien reçu votre demande!</div>.';
                }
            }

        } else {
            echo 'Sorry, missing parameter...';
        }

        ?>
    </div>

</div><!-- /.container -->


<!-- Bootstrap core JavaScript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
<script src="js/jquery-3.1.1.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>





