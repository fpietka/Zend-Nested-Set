--
-- PostgreSQL database dump
--

SET client_encoding = 'UTF8';
SET check_function_bodies = false;
SET client_min_messages = warning;

SET search_path = public, pg_catalog;

DROP INDEX public.example_unique_idx;
DROP INDEX public.example__string__idx;
ALTER TABLE ONLY public.example_type DROP CONSTRAINT example_type_example_type_id_key;
ALTER TABLE ONLY public.example DROP CONSTRAINT example_example_id_key;
DROP TABLE public.example_type;
DROP TABLE public.example;
DROP SCHEMA public;
--
-- Name: public; Type: SCHEMA; Schema: -; Owner: dev
--

CREATE SCHEMA public;


ALTER SCHEMA public OWNER TO dev;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: example; Type: TABLE; Schema: public; Owner: dev; Tablespace:
--

CREATE TABLE example (
    example_id serial NOT NULL,
    example_type_id integer DEFAULT 1 NOT NULL,
    unique_string text NOT NULL,
    string text NOT NULL,
    state smallint DEFAULT 1 NOT NULL,
    update timestamp without time zone,
    creation timestamp without time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.example OWNER TO dev;

--
-- Name: example_type; Type: TABLE; Schema: public; Owner: dev; Tablespace:
--

CREATE TABLE example_type (
    example_type_id serial NOT NULL,
    label text NOT NULL,
    short_label text NOT NULL,
    creation timestamp without time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.example_type OWNER TO dev;

--
-- Name: example_example_id_key; Type: CONSTRAINT; Schema: public; Owner: dev; Tablespace:
--

ALTER TABLE ONLY example
    ADD CONSTRAINT example_example_id_key UNIQUE (example_id);


--
-- Name: example_type_example_type_id_key; Type: CONSTRAINT; Schema: public; Owner: dev; Tablespace:
--

ALTER TABLE ONLY example_type
    ADD CONSTRAINT example_type_example_type_id_key UNIQUE (example_type_id);


--
-- Name: example__string__idx; Type: INDEX; Schema: public; Owner: dev; Tablespace:
--

CREATE INDEX example__string__idx ON example USING btree (string);


--
-- Name: example_unique_idx; Type: INDEX; Schema: public; Owner: dev; Tablespace:
--

CREATE UNIQUE INDEX example_unique_idx ON example USING btree (unique_string);


--
-- PostgreSQL database dump complete
--

