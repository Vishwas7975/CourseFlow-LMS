<?php

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=student_sample.csv");

echo "certificate_number,name,email,mobile,college,dept,passout_year,hall_ticket,internship_course,start_date,end_date,download_link,verification_symbol\n";

echo "VAI-AI-001,John Doe,john@gmail.com,9876543210,ABC College,CSE,2025,22CS101,Cyber Security,2026-01-01,2026-03-01,https://drive.link,Verified\n";

exit();

?>