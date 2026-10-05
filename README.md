# PHP Sunucu Bilgi Scripti

PHP ile çalışan web sunucunuzun ve PHP ortamınızın ayrıntılı raporunu oluşturur. PHP sürümü ve SAPI, önemli çalışma limitleri, yüklü uzantılar, sistem belleği/yükü/çalışma süresi ve yaygın servislerin yerel port durumu tek sayfada gösterilir.

## Kullanım

1. `index.php` dosyasını sunucunuzdaki PHP ile çalışan web dizinine yükleyin.
2. Güvenli bir yöntemle yalnızca sunucu üzerinden erişin. Kimlik bilgileri tanımlanmamışken script uzaktan gelen istekleri reddeder.
3. Uzaktan erişim gerekiyorsa HTTPS kullanın ve web sunucusunun ortamında `SERVER_INFO_USERNAME` ve `SERVER_INFO_PASSWORD` değişkenlerini güçlü, ayrıcalıklı olmayan kimlik bilgileriyle ayarlayın. Script uzaktan HTTP bağlantılarını reddeder ve HTTPS üzerinden HTTP Basic Authentication ister; parolayı URL'ye veya dosyaya yazmayın.
4. Raporu aldıktan sonra `index.php` dosyasını sunucudan kaldırın.

## Servis kontrollerinin kapsamı

Yaygın servisler (SSH, FTP, SMTP, DNS, HTTP(S), MySQL/MariaDB, PostgreSQL, Redis, Memcached, Elasticsearch, MongoDB, RabbitMQ ve Kafka) yalnızca `127.0.0.1` üzerindeki bilinen TCP portlarında kontrol edilir. Erişilebilir port servisin yanıt verdiğini gösterir. “Tespit edilemedi” sonucu servisin kesinlikle çalışmadığını göstermez: özel port/adres, PHP kısıtlamaları veya paylaşımlı barındırma nedeniyle durum belirlenemeyebilir. Script işletim sistemi genelindeki bütün servisleri kesin olarak listeleyemez.

Rapor hassas ortam değişkenlerini, HTTP başlıklarını veya yapılandırma dosyalarının içeriklerini göstermez. Yine de sunucu/yazılım bilgileri içerdiğinden herkese açık bırakmayın. PHP yapılandırmasında `fsockopen` kapalıysa port kontrolleri yapılamaz.
