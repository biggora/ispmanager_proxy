/* DROP TABLE IF EXISTS "proxies"; */
CREATE TABLE IF NOT EXISTS "proxies" (
  "id" INTEGER PRIMARY KEY NOT NULL,
  "active" VARCHAR(3) DEFAULT "on",
  "owner" VARCHAR(150) NOT NULL,
  "currname" INTEGER,
  "domain" VARCHAR(255) NOT NULL,
  "aliases" VARCHAR(250),
  "ipaddrs" VARCHAR(250),
  "redirect_type" VARCHAR(50) DEFAULT "proxy",
  "redirect_path" VARCHAR(150),
  "redirect_seo" VARCHAR(150),
  "proxy_rules" TEXT,
  "rewrite_rules" TEXT,
  "limit_ssl" VARCHAR(3) DEFAULT "on",
  "secure" VARCHAR(3) DEFAULT "off",
  "redirect_http" VARCHAR(3) DEFAULT "off",
  "strict_ssl" VARCHAR(3) DEFAULT "off",
  "ssl_port" INTEGER NOT NULL DEFAULT (443),
  "ssl_cert" VARCHAR(250),
  "ddosshield" VARCHAR(3) DEFAULT "off",
  "nginx_limitrequest" INTEGER,
  "nginx_burstrequest" INTEGER
);
CREATE UNIQUE INDEX IF NOT EXISTS "main"."idx_proxy" ON "proxies" ("domain" ASC);
