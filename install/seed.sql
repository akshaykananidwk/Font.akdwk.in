-- ગુજરાતી ફોન્ટ કન્વર્ટર — Default Seed Data
-- Admin account installer બનાવે છે (અહીં નહીં).

SET NAMES utf8mb4;

-- Languages
INSERT INTO `{{prefix}}languages` (id, name, code, native_name, unicode_font, is_active, sort_order) VALUES
(1, 'Gujarati', 'gu', 'ગુજરાતી', 'Shruti', 1, 1),
(2, 'Hindi', 'hi', 'हिन्दी', 'Mangal', 1, 2),
(3, 'Marathi', 'mr', 'मराठी', 'Mangal', 1, 3),
(4, 'Nepali', 'ne', 'नेपाली', 'Mangal', 1, 4)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Settings
INSERT INTO `{{prefix}}settings` (setting_key, setting_value, setting_group) VALUES
('site_name', 'Gujarati Font Converter', 'general'),
('site_tagline', 'Free Online Gujarati Font Converter', 'general'),
('demo_char_limit', '200', 'general'),
('demo_attempt_limit', '20', 'general'),
('rate_limit_per_min', '30', 'general'),
('maintenance_mode', '0', 'general'),
('default_meta_title', 'Gujarati Font Converter — Legacy to Unicode (LMG, Shree Guj, Kruti Dev)', 'seo'),
('default_meta_description', 'Free online Gujarati font converter. Convert LMG, Shree Guj, Saral, Terafont, Akruti and 90+ legacy fonts to Unicode (Shruti) and back.', 'seo'),
('google_analytics_id', '', 'seo'),
('google_site_verification', '', 'seo'),
('bing_site_verification', '', 'seo'),
('email_from', '', 'email'),
('smtp_enabled', '0', 'email'),
('smtp_host', '', 'email'),
('smtp_port', '587', 'email'),
('smtp_user', '', 'email'),
('smtp_pass', '', 'email'),
('smtp_encryption', 'tls', 'email'),
('github_repo', '', 'update'),
('github_branch', 'main', 'update'),
('github_token_encrypted', '', 'update'),
('update_check_frequency', 'manual', 'update'),
('api_enabled', '1', 'api'),
('api_free_daily_limit', '100', 'api')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- Plans
INSERT INTO `{{prefix}}plans` (id, plan_name, price, currency, duration_days, char_limit, daily_limit, api_access, api_daily_limit, features, is_active, sort_order) VALUES
(1, 'Free Demo', 0.00, 'INR', 36500, 200, 20, 0, 0, '["Up to 200 characters", "20 attempts/day", "All fonts"]', 1, 1),
(2, 'Basic', 99.00, 'INR', 30, 0, 100, 0, 0, '["Unlimited characters", "100 conversions/day", "File upload"]', 1, 2),
(3, 'Pro', 299.00, 'INR', 30, 0, 0, 1, 1000, '["Unlimited conversions", "API access - 1000 calls/day", "File upload", "Priority support"]', 1, 3),
(4, 'Business', 999.00, 'INR', 30, 0, 0, 1, 10000, '["Everything unlimited", "API - 10,000 calls/day", "Batch API", "Dedicated support"]', 1, 4)
ON DUPLICATE KEY UPDATE plan_name = VALUES(plan_name);

-- Static pages
INSERT INTO `{{prefix}}pages` (slug, title, content, meta_title, meta_description, status, sort_order) VALUES
('faq', 'FAQ - વારંવાર પૂછાતા પ્રશ્નો', '<h2>વારંવાર પૂછાતા પ્રશ્નો</h2><div class="faq-item"><h3>આ ટૂલ મફત છે?</h3><p>હા, 200 અક્ષર સુધીનું કન્વર્ઝન સંપૂર્ણ મફત છે. વધુ માટે અમારા સસ્તા પ્લાન જુઓ.</p></div><div class="faq-item"><h3>મારો ટેક્સ્ટ સર્વર પર સ્ટોર થાય છે?</h3><p>ના. કન્વર્ટ થતો ટેક્સ્ટ ક્યારેય સ્ટોર થતો નથી — માત્ર અક્ષરોની સંખ્યા આંકડાકીય હેતુ માટે નોંધાય છે.</p></div><div class="faq-item"><h3>કયા ફોન્ટ સપોર્ટ થાય છે?</h3><p>LMG, Shree Guj, Saral, Terafont, Akruti, Gujlys, EKLG, Bhasha Bharti, Sulekh, ISM સહિત 90+ ફોન્ટ.</p></div><div class="faq-item"><h3>Unicode ફોન્ટ શા માટે વાપરવો જોઈએ?</h3><p>Unicode (Shruti/Nirmala UI) દરેક device પર દેખાય છે, Google માં search થાય છે અને હંમેશ માટે readable રહે છે.</p></div>', 'FAQ | Gujarati Font Converter', 'ગુજરાતી ફોન્ટ કન્વર્ટર વિશે વારંવાર પૂછાતા પ્રશ્નો — મફત મર્યાદા, પ્રાઇવસી, સપોર્ટેડ ફોન્ટ અને વધુ.', 'published', 1),
('privacy-policy', 'Privacy Policy - પ્રાઇવસી પોલિસી', '<h2>પ્રાઇવસી પોલિસી</h2><p><strong>તમારો ટેક્સ્ટ:</strong> કન્વર્ઝન માટે મોકલાયેલો ટેક્સ્ટ ક્યારેય અમારા સર્વર પર સ્ટોર થતો નથી, કોઈ ડેટાબેઝ કે લોગ ફાઇલમાં લખાતો નથી. ફક્ત અક્ષરોની સંખ્યા (character count) આંકડાકીય હેતુ માટે નોંધાય છે.</p><p><strong>IP Address:</strong> demo limit અને security માટે IP address અસ્થાયી રૂપે નોંધાય છે.</p><p><strong>Cookies:</strong> session અને preferences (dark mode વગેરે) માટે જ વપરાય છે.</p><p><strong>ત્રીજા પક્ષ:</strong> અમે તમારો કોઈ ડેટા વેચતા નથી.</p>', 'Privacy Policy | Gujarati Font Converter', 'અમારી પ્રાઇવસી પોલિસી — તમારો કન્વર્ટ થતો ટેક્સ્ટ ક્યારેય સ્ટોર થતો નથી. વિગતો અહીં વાંચો.', 'published', 2),
('terms', 'Terms of Service - નિયમો અને શરતો', '<h2>નિયમો અને શરતો</h2><p>આ વેબસાઇટ વાપરીને તમે નીચેની શરતો સ્વીકારો છો:</p><ul><li>સેવા "જેમ છે તેમ" (as-is) ધોરણે અપાય છે.</li><li>કન્વર્ઝન પરિણામની 100% ચોકસાઈની ગેરંટી નથી — મહત્વના દસ્તાવેજ હંમેશા ચકાસો.</li><li>Automated abuse, scraping કે bulk requests API વગર કરવા પ્રતિબંધિત છે.</li><li>Paid plans ના payment અંતિમ છે; refund policy સંપર્ક પેજ થી જાણો.</li></ul>', 'Terms of Service | Gujarati Font Converter', 'ગુજરાતી ફોન્ટ કન્વર્ટર વાપરવાના નિયમો અને શરતો.', 'published', 3),
('font-installation-guide', 'ફોન્ટ Installation ગાઇડ', '<h2>ગુજરાતી ફોન્ટ કેવી રીતે Install કરવો</h2><h3>Windows માં:</h3><ol><li>ફોન્ટ ફાઇલ (.ttf) ડાઉનલોડ કરો</li><li>ફાઇલ પર right-click કરીને "Install" પસંદ કરો</li><li>અથવા Control Panel → Fonts માં ફાઇલ copy કરો</li></ol><h3>Unicode ફોન્ટ (ભલામણ):</h3><p>Windows માં Shruti ફોન્ટ પહેલેથી હોય છે. Nirmala UI (Windows 8+) પણ સરસ વિકલ્પ છે.</p><h3>Mac માં:</h3><ol><li>ફોન્ટ ફાઇલ ડબલ-ક્લિક કરો</li><li>"Install Font" બટન દબાવો</li></ol>', 'ગુજરાતી ફોન્ટ Installation ગાઇડ | Font Converter', 'Windows અને Mac માં ગુજરાતી ફોન્ટ કેવી રીતે install કરવો — સરળ પગલાં-દર-પગલાં ગાઇડ.', 'published', 4)
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Blog category
INSERT INTO `{{prefix}}blog_categories` (id, name, slug, description) VALUES
(1, 'Tutorials', 'tutorials', 'Font conversion and typing tutorials')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO `{{prefix}}fonts` (language_id, font_name, font_slug, mapping_file, font_family, is_active, is_popular, sort_order) VALUES
(1, 'LMG Arun', 'lmg', 'gujarati/lmg.json', 'LMG Arun', 1, 1, 10),
(1, 'LMG Rupen', 'lmg-rupen', 'gujarati/lmg_rupen.json', 'LMG Rupen', 1, 0, 20),
(1, 'LMG Kartik', 'lmg-kartik', 'gujarati/lmg_kartik.json', 'LMG Kartik', 1, 0, 30),
(1, 'LMG Laxmi', 'lmg-laxmi', 'gujarati/lmg_laxmi.json', 'LMG Laxmi', 1, 0, 40),
(1, 'Shree Guj 0768', 'shree-guj-0768', 'gujarati/shree_guj_0768.json', 'Shree Guj 0768', 1, 1, 50),
(1, 'Shree Guj 1110', 'shree-guj-1110', 'gujarati/shree_guj_1110.json', 'Shree Guj 1110', 1, 0, 60),
(1, 'Shree Guj 2618', 'shree-guj-2618', 'gujarati/shree_guj_2618.json', 'Shree Guj 2618', 1, 0, 70),
(1, 'Shree Guj 3392', 'shree-guj-3392', 'gujarati/shree_guj_3392.json', 'Shree Guj 3392', 1, 0, 80),
(1, 'Shree Guj 5008', 'shree-guj-5008', 'gujarati/shree_guj_5008.json', 'Shree Guj 5008', 1, 0, 90),
(1, 'Shree Lipi Gujarati', 'shree-lipi-guj', 'gujarati/shree_lipi_guj.json', 'Shree Lipi Gujarati', 1, 1, 100),
(1, 'Saral', 'saral', 'gujarati/saral.json', 'Saral', 1, 1, 110),
(1, 'Saral 2', 'saral-2', 'gujarati/saral_2.json', 'Saral 2', 1, 0, 120),
(1, 'Terafont Varun', 'terafont-varun', 'gujarati/terafont_varun.json', 'Terafont Varun', 1, 1, 130),
(1, 'Terafont Akash', 'terafont-akash', 'gujarati/terafont_akash.json', 'Terafont Akash', 1, 0, 140),
(1, 'Terafont Chandan', 'terafont-chandan', 'gujarati/terafont_chandan.json', 'Terafont Chandan', 1, 0, 150),
(1, 'Terafont Kavya', 'terafont-kavya', 'gujarati/terafont_kavya.json', 'Terafont Kavya', 1, 0, 160),
(1, 'Terafont Trilochan', 'terafont-trilochan', 'gujarati/terafont_trilochan.json', 'Terafont Trilochan', 1, 0, 170),
(1, 'Akruti Gujarati', 'akruti-guj', 'gujarati/akruti_guj.json', 'Akruti Gujarati', 1, 1, 180),
(1, 'Akruti Gujarati 2', 'akruti-guj-2', 'gujarati/akruti_guj_2.json', 'Akruti Gujarati 2', 1, 0, 190),
(1, 'Akruti Mogra', 'akruti-mogra', 'gujarati/akruti_mogra.json', 'Akruti Mogra', 1, 0, 200),
(1, 'Akruti Priya', 'akruti-priya', 'gujarati/akruti_priya.json', 'Akruti Priya', 1, 0, 210),
(1, 'Gujlys', 'gujlys', 'gujarati/gujlys.json', 'Gujlys', 1, 1, 220),
(1, 'Gujlys 2', 'gujlys-2', 'gujarati/gujlys_2.json', 'Gujlys 2', 1, 0, 230),
(1, 'EKLG', 'eklg', 'gujarati/eklg.json', 'EKLG', 1, 1, 240),
(1, 'EKLG 2', 'eklg-2', 'gujarati/eklg_2.json', 'EKLG 2', 1, 0, 250),
(1, 'EKLG TBold', 'eklg-tbold', 'gujarati/eklg_tbold.json', 'EKLG TBold', 1, 0, 260),
(1, 'Bhasha Bharti', 'bhasha-bharti', 'gujarati/bhasha_bharti.json', 'Bhasha Bharti', 1, 1, 270),
(1, 'Bhasha Bharti 2', 'bhasha-bharti-2', 'gujarati/bhasha_bharti_2.json', 'Bhasha Bharti 2', 1, 0, 280),
(1, 'Sulekh', 'sulekh', 'gujarati/sulekh.json', 'Sulekh', 1, 1, 290),
(1, 'Sulekh Bold', 'sulekh-bold', 'gujarati/sulekh_bold.json', 'Sulekh Bold', 1, 0, 300),
(1, 'ISM Gujarati', 'ism-guj', 'gujarati/ism_guj.json', 'ISM Gujarati', 1, 0, 310),
(1, 'ISM V6 Gujarati', 'ism-v6-guj', 'gujarati/ism_v6_guj.json', 'ISM V6 Gujarati', 1, 0, 320),
(1, 'Ghanshyam', 'ghanshyam', 'gujarati/ghanshyam.json', 'Ghanshyam', 1, 0, 330),
(1, 'Gopika', 'gopika', 'gujarati/gopika.json', 'Gopika', 1, 0, 340),
(1, 'Harikrishna', 'harikrishna', 'gujarati/harikrishna.json', 'Harikrishna', 1, 0, 350),
(1, 'Avantika', 'avantika', 'gujarati/avantika.json', 'Avantika', 1, 0, 360),
(1, 'Amrut', 'amrut', 'gujarati/amrut.json', 'Amrut', 1, 0, 370),
(1, 'Divya', 'divya', 'gujarati/divya.json', 'Divya', 1, 0, 380),
(1, 'Nilkanth', 'nilkanth', 'gujarati/nilkanth.json', 'Nilkanth', 1, 0, 390),
(1, 'Sugam', 'sugam', 'gujarati/sugam.json', 'Sugam', 1, 0, 400),
(1, 'Gujarati Saral-1', 'gujarati-saral-1', 'gujarati/gujarati_saral_1.json', 'Gujarati Saral-1', 1, 0, 410),
(1, 'Gujarati Saral-2', 'gujarati-saral-2', 'gujarati/gujarati_saral_2.json', 'Gujarati Saral-2', 1, 0, 420),
(1, 'Krishna Gujarati', 'krishna-guj', 'gujarati/krishna_guj.json', 'Krishna Gujarati', 1, 0, 430),
(1, 'Gopi Gujarati', 'gopi-guj', 'gujarati/gopi_guj.json', 'Gopi Gujarati', 1, 0, 440),
(1, 'Shivam Gujarati', 'shivam-guj', 'gujarati/shivam_guj.json', 'Shivam Gujarati', 1, 0, 450),
(1, 'Satyam Gujarati', 'satyam-guj', 'gujarati/satyam_guj.json', 'Satyam Gujarati', 1, 0, 460),
(1, 'Sundaram Gujarati', 'sundaram-guj', 'gujarati/sundaram_guj.json', 'Sundaram Gujarati', 1, 0, 470),
(1, 'Ajay Gujarati', 'ajay-guj', 'gujarati/ajay_guj.json', 'Ajay Gujarati', 1, 0, 480),
(1, 'Vijay Gujarati', 'vijay-guj', 'gujarati/vijay_guj.json', 'Vijay Gujarati', 1, 0, 490),
(1, 'Anand Gujarati', 'anand-guj', 'gujarati/anand_guj.json', 'Anand Gujarati', 1, 0, 500),
(1, 'Rachana Gujarati', 'rachana-guj', 'gujarati/rachana_guj.json', 'Rachana Gujarati', 1, 0, 510),
(1, 'Vakil Gujarati', 'vakil-guj', 'gujarati/vakil_guj.json', 'Vakil Gujarati', 1, 0, 520),
(1, 'PageMaker Gujarati', 'page-maker-guj', 'gujarati/page_maker_guj.json', 'PageMaker Gujarati', 1, 0, 530),
(1, 'Corel Gujarati', 'corel-guj', 'gujarati/corel_guj.json', 'Corel Gujarati', 1, 0, 540),
(2, 'Kruti Dev 010', 'kruti-dev-010', 'hindi/kruti_dev_010.json', 'Kruti Dev 010', 1, 1, 10),
(2, 'Kruti Dev 016', 'kruti-dev-016', 'hindi/kruti_dev_016.json', 'Kruti Dev 016', 1, 0, 20),
(2, 'Kruti Dev 021', 'kruti-dev-021', 'hindi/kruti_dev_021.json', 'Kruti Dev 021', 1, 0, 30),
(2, 'Kruti Dev 040', 'kruti-dev-040', 'hindi/kruti_dev_040.json', 'Kruti Dev 040', 1, 0, 40),
(2, 'Kruti Dev 055', 'kruti-dev-055', 'hindi/kruti_dev_055.json', 'Kruti Dev 055', 1, 0, 50),
(2, 'Chanakya', 'chanakya', 'hindi/chanakya.json', 'Chanakya', 1, 1, 60),
(2, 'Walkman Chanakya', 'walkman-chanakya', 'hindi/walkman_chanakya.json', 'Walkman Chanakya', 1, 0, 70),
(2, 'DevLys 010', 'devlys-010', 'hindi/devlys_010.json', 'DevLys 010', 1, 1, 80),
(2, 'DevLys 020', 'devlys-020', 'hindi/devlys_020.json', 'DevLys 020', 1, 0, 90),
(2, 'DevLys 030', 'devlys-030', 'hindi/devlys_030.json', 'DevLys 030', 1, 0, 100),
(2, 'Shusha', 'shusha', 'hindi/shusha.json', 'Shusha', 1, 0, 110),
(2, 'Agra', 'agra', 'hindi/agra.json', 'Agra', 1, 0, 120),
(2, 'Shree Dev 0714', 'shree-dev-0714', 'hindi/shree_dev_0714.json', 'Shree Dev 0714', 1, 0, 130),
(2, 'Shree Dev 1001', 'shree-dev-1001', 'hindi/shree_dev_1001.json', 'Shree Dev 1001', 1, 0, 140),
(2, 'Shree Dev 2405', 'shree-dev-2405', 'hindi/shree_dev_2405.json', 'Shree Dev 2405', 1, 0, 150),
(2, 'Akruti Dev', 'akruti-dev', 'hindi/akruti_dev.json', 'Akruti Dev', 1, 0, 160),
(2, 'Akruti Dev Priya', 'akruti-dev-priya', 'hindi/akruti_dev_priya.json', 'Akruti Dev Priya', 1, 0, 170),
(2, 'Aman', 'aman', 'hindi/aman.json', 'Aman', 1, 0, 180),
(2, 'Amar', 'amar', 'hindi/amar.json', 'Amar', 1, 0, 190),
(2, 'Arjun', 'arjun', 'hindi/arjun.json', 'Arjun', 1, 0, 200),
(2, 'Bahar', 'bahar', 'hindi/bahar.json', 'Bahar', 1, 0, 210),
(2, 'Chandini', 'chandini', 'hindi/chandini.json', 'Chandini', 1, 0, 220),
(2, 'ISM Dev', 'ism-dev', 'hindi/ism_dev.json', 'ISM Dev', 1, 0, 230),
(2, 'Terafont Dev', 'terafont-dev', 'hindi/terafont_dev.json', 'Terafont Dev', 1, 0, 240),
(2, 'Richa', 'richa', 'hindi/richa.json', 'Richa', 1, 0, 250),
(2, 'Yogesh', 'yogesh', 'hindi/yogesh.json', 'Yogesh', 1, 0, 260),
(3, 'Shree Dev Marathi', 'shree-dev-marathi', 'marathi/shree_dev_marathi.json', 'Shree Dev Marathi', 1, 1, 10),
(3, 'Shivaji', 'shivaji', 'marathi/shivaji.json', 'Shivaji', 1, 1, 20),
(3, 'Shivaji 01', 'shivaji-01', 'marathi/shivaji_01.json', 'Shivaji 01', 1, 0, 30),
(3, 'Shivaji 02', 'shivaji-02', 'marathi/shivaji_02.json', 'Shivaji 02', 1, 0, 40),
(3, 'Kiran', 'kiran', 'marathi/kiran.json', 'Kiran', 1, 0, 50),
(3, 'Yashomudra', 'yashomudra', 'marathi/yashomudra.json', 'Yashomudra', 1, 0, 60),
(3, 'Vakil Marathi', 'vakil-marathi', 'marathi/vakil_marathi.json', 'Vakil Marathi', 1, 0, 70),
(3, 'Akruti Marathi', 'akruti-marathi', 'marathi/akruti_marathi.json', 'Akruti Marathi', 1, 0, 80),
(3, 'ISM Marathi', 'ism-marathi', 'marathi/ism_marathi.json', 'ISM Marathi', 1, 0, 90),
(4, 'Preeti', 'preeti', 'nepali/preeti.json', 'Preeti', 1, 1, 10),
(4, 'Kantipur', 'kantipur', 'nepali/kantipur.json', 'Kantipur', 1, 1, 20),
(4, 'Himali', 'himali', 'nepali/himali.json', 'Himali', 1, 0, 30),
(4, 'Sagarmatha', 'sagarmatha', 'nepali/sagarmatha.json', 'Sagarmatha', 1, 0, 40),
(4, 'Aakriti', 'aakriti', 'nepali/aakriti.json', 'Aakriti', 1, 0, 50);
