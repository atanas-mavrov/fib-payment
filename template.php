<html>
    <head>
         <meta charset="utf-8" />
         <?php if($error) {
             echo '<title>Payment error - Example of FIB Internet Payment</title>';
         } else if ($status) {
             if($status == 1) {
                 $stat = 'Sucessful';
             } elseif($status == 2) {
                 $stat = 'Unsucessful';
             } else {
                 $stat = 'Pending/Unknow';
             }
             echo '<title>Payment status '.$stat.' - Example of FIB Internet Payment</title>';
         } else {
             echo '<title>Example of FIB Internet Payment</title>';
         }
         ?>
         
    </head>
    <body>
        
        <?php if($error) { ?>
            Payment Error!
            <br />
            Error message: <?php echo $error; ?>
        <?php } else if ($status) { ?>
            Your payment is <?php echo $stat; ?>
        <?php } else { ?>
        <form method="post">
            Form with goods!
            <br />
            Please make payment with bank card!
            <br />
            <input type="submit" value="Payment" name="tobank" />
        </form>        
        <?php } ?>
    </body>
</html>